# Contact Data Spec — the rules we rely on

This is the **canonical contract** for how contact data enters, lives in, and leaves the
system. It exists because we kept fixing the same class of bug one record at a time
(stub-name merges, export filter drift, emirate noise, split mislabeling). Those were all
symptoms of missing foundations, not bad luck. This document defines the foundations; the
edge-case registry ([`imports.md`](imports.md) / [`exports.md`](exports.md)) records each
specific incident that the rules here prevent.

Status: **draft for review.** Sections marked **(target)** are not built yet; **(exists)**
describes today's code; **(gap)** is the delta we intend to close.

---

## 1. The mental model

Three things that are currently fused must be kept distinct:

1. **Raw row** — a line exactly as it arrived in an import, with full provenance (file, row
   number, batch, ingested-at). *Immutable, append-only.* Everything else is derived from it
   and can be rebuilt by reprocessing.
2. **Contact** — a **phone number**. This is the atomic, reliable unit. It is the thing we
   actually call or message, and it carries its own suppression / eligibility / cooldown /
   campaign history. A phone number identifies a *contact point*.
3. **Person (client)** — a *derived* identity that may own **several** contacts. A person is
   a **clustering of contacts** with a confidence score — not a primary fact.

> Today `clients` (person) and `client_phone_numbers` (contact) already exist as separate
> tables (**exists**), and `client_sources.metadata` already preserves the raw row
> (**exists**). What's missing is treating the person as *derived and reviewable* rather than
> something an importer writes directly (**gap**).

### The one principle that prevents the worst bugs

**Over-merging is destructive; under-merging is cheap.** Collapsing two real people into one
client blends their attributes irreversibly (the stub-merge disaster, the FOUAD mislabel).
Leaving one person as two clients is trivially fixed later with a deliberate merge. **Therefore
we always bias toward NOT merging.** When confidence is low, create-new or send-to-review —
never guess-and-merge.

---

## 2. Core invariants

These hold everywhere. A violation is a bug, and ideally a failing test or a dashboard alert.

- **INV-1 — Phone is identity; name is never an identity key.** Match a row to an existing
  contact by canonical `normalized_phone` only. Names collide (two people, same name), so a
  name match alone must never link or merge. *(Root cause of IMP-001.)*
- **INV-2 — One canonical phone normalization.** A single normalizer produces the canonical
  `+E.164` form used for the unique index and all matching. *(Gap: two normalizers today —
  see §7.)*
- **INV-3 — Raw is never mutated.** Imported rows are preserved verbatim in the raw/provenance
  layer. Cleaning is derived and reproducible; we fix rules and reprocess, never hand-patch
  raw.
- **INV-4 — Provenance on every value.** For any field on a person (name, emirate, …) we can
  answer "which source set this, and when." *(Partially exists via `client_sources.metadata`.)*
- **INV-5 — Suppression lives on the contact, never the person.** DNC / Do-Not-Message is a
  property of a phone number. An identity-resolution mistake can therefore never cause us to
  message a suppressed number.
- **INV-6 — Ambiguity is reviewed, not guessed.** Cases below the confidence threshold
  (possible duplicate, conflicting name, institution/shared line, multi-name) go to the review
  queue — they are not auto-merged. *(Gap: queue exists but is dormant — see §6.)*
- **INV-7 — Every bulk write is reversible.** Dry-run → backup → `client_audit_logs` →
  reversible. No exceptions. *(See §9.)*
- **INV-8 — Exports derive from the live filtered query and exclude active suppressions.**
  Never re-implement filters. *(EXP-001 — see §8.)*

---

## 3. Source trust & survivorship

When sources disagree about a field, we do **not** "always overwrite" or "always keep." We
apply **survivorship rules** driven by a **source trust hierarchy** (most-trusted first):

1. Human-verified / manual admin entry
2. Verified CRM / lead record (has corroborating email or ID)
3. Campaign result (the person responded)
4. Raw import with a real name
5. Raw import with a stub/placeholder name *(never wins a populated field)*

Survivorship rules:

- A higher-trust source **may** overwrite a lower-trust value; a lower-trust source **never**
  overwrites a higher-trust one.
- A **real name never loses to a stub** (INV-1 corollary).
- Among equal trust, prefer **more recent** then **more complete**.
- The losing value is **never destroyed** — it is retained in provenance
  (`client_sources.metadata.raw_name`, `clients.alternate_names`) so the "winner" is just a
  *view* and is always re-derivable. *(alternate_names exists; explicit trust ranking is a
  gap.)*

---

## 4. Identity resolution — the decision rules

Given one cleaned import row, resolve it to a contact + person in this order. This is the
heart of the "algorithm," and it directly answers the three policy questions:

```
1. Normalize the phone (INV-2). Invalid/placeholder phone  → quarantine row (review).
2. Phone already exists as a Contact?
     YES → attach to that Contact's Person. Enrich blank fields per survivorship (§3).
           If the row's real name CONFLICTS with the stored real name → record as an
           alternate name AND raise a review item (do not overwrite). [Q3]
     NO  → continue.
3. Name is a stub/placeholder (NameClassifier, §5)?
     YES → create a FRESH Person for this new Contact. Never match by stub name. [IMP-001]
4. Name looks like an INSTITUTION (§5)?
     YES → create a FRESH Person too. An institution name is the worst anchor — a bank is the
           registered owner of hundreds of unrelated properties — so it never merges. [IMP-003]
           (A known SHARED LINE is still flagged via is_shared_line and routed to review.)
5. Otherwise (a real personal name, new phone):
     Default = create a NEW Person (phone is identity; same name ≠ same person). [Q2]
     Only link this new phone to an EXISTING person on a STRONG signal:
       - shared email / Emirates ID / CRM lead-id, OR
       - an explicit human/source assertion, OR
       - the same number reappearing.
     A name match alone is NOT a strong signal → leave separate, optionally flag as a
     possible-duplicate for review (cheap to merge later; INV-6, the asymmetry principle).
```

**Answer to Q2 (one person, many numbers):** we never *infer* "same person" from a shared
name. Multiple numbers attach to one person only on a strong, deterministic signal; otherwise
they stay as separate people and surface as a possible-duplicate review item. Merging is a
deliberate, reversible operation (`clients:merge`, §9) — never an import side effect.

**Answer to Q3 (import name conflicts with stored name):** neither blind-overwrite nor
blind-keep. Apply survivorship (§3): keep the higher-trust value as the display name, retain
the other as an alternate, and **raise a review item** so a human can confirm whether it's the
same person, two people sharing a number, or a data error.

> **(done)** Steps 2–5 are implemented in `RawContactImportEnricher::resolveClient()`: phone
> match first, then create-fresh for stub (IMP-001), real (IMP-002), and institution (IMP-003)
> names alike — no name-tuple `firstOrCreate` remains. The strong-signal linking in step 5 and
> the review-queue routing are still to come. The IVR *bulk* path
> (`RawImportProcessor::assignClients()`) now creates fresh per phone too (IMP-004) — all import
> paths resolve identity identically.

---

## 5. Classifying a name / number

Centralized, single implementation, with a confidence label. *(Today: `isStubName()` in
`RawContactImportEnricher`; target: a dedicated `NameClassifier` used by import, audit, and
review alike.)*

- **Stub / placeholder** — generic labels (DND, N/A, UNKNOWN), source/channel labels leaked
  into the name field ("Instagram DM", "PF Call", "WhatsApp From Bayut"), truncated names,
  single-word-only, repeated-word stubs ("Finder Finder", "Pflead Pflead"). **Never an
  identity key.** *(Repeated-word and a few labels are known gaps — see IMP-001 "watch out
  for".)*
- **Institution** — bank / developer / agency / hotline names. Real entities, but they must
  **never anchor identity**: on import an institution name now creates a fresh client per phone
  exactly like a stub (IMP-003), because in owner data a bank is the registered owner of hundreds
  of unrelated properties. A genuine shared/reception line is flagged separately
  (`client_phone_numbers.is_shared_line`, set by `clients:mark-shared-line`) and excluded from
  person-merging. If a true "organisation" entity is ever needed, it belongs in its own table,
  not as a merge anchor in the contact graph.
- **Real personal name** — eligible for survivorship and strong-signal linking.

---

## 6. The review queue (make it live)

`ImportStaging` + `ImportReviewQueue` + their Filament UI already exist (**exists**), but the
high-volume IVR/WhatsApp importers bypass them and the queue only handles geography issues
(**gap**). Target:

- **Every import flows through staging** (raw → cleaned → resolved), not just name-only rows.
- The review queue receives **identity** cases too, with a typed `reason`:
  `possible_duplicate`, `name_conflict`, `institution`, `multi_name`, `invalid_phone`,
  plus the existing geography reasons.
- Each item shows the conflicting values **side by side with their provenance**, and offers
  actions: *create new*, *merge into …*, *keep separate*, *pick winning value*, *mark
  institution / shared line*. These reuse `clients:merge` / `clients:mark-shared-line`
  semantics so CLI and UI stay consistent.
- The confident majority auto-resolves; only the ambiguous tail (~a few %) is queued. A
  permanent review queue is expected, not a failure.

---

## 7. Phone normalization (unify)

Two normalizers exist: `Modules/IVR/Support/PhoneNormalizer` (hand-rolled UAE logic) and
`Modules/WhatsApp/Support/WhatsAppPhoneNormalizer` (libphonenumber, stricter). They can
disagree, which splits or leaks identity across channels (**gap**). Target: **one canonical
normalizer** (libphonenumber-based, with our UAE defaults), used by every import, filter,
export, and the unique index. The other becomes a thin alias during migration, then is removed.

---

## 8. Export invariants

- Build from the **table's `getFilteredTableQuery()`** — never hand-rebuild filters. *(EXP-001.)*
- Always **exclude active suppressions** for the channel.
- "Dubai / UAE" means **`is_uae = true`**, not just the `emirate` text field (foreign numbers
  can carry a stale emirate). *(Lesson from the emirate backfill incident.)*
- Every export is **batch-tracked** so a send can be reconstructed and de-duplicated.

---

## 9. Bulk-operation protocol (INV-7)

Every command or UI action that writes to many records must:

1. **Dry-run by default**; require `--apply` (or an explicit confirm) to write.
2. Write a **backup CSV** of the before-state to `storage/app/backups/`.
3. Write a **`client_audit_logs`** summary row (action, reason, performed_by, snapshot,
   backup path) — within the same transaction as the change.
4. Be **reversible** from that backup + audit row.

`clients:merge` and `clients:set-emirate-from-csv` already follow this (**exists**);
`clients:normalize-names`, `clients:split-name-collisions`, and any new bulk op must too
(**gap — audit logging inconsistent today**).

---

## 10. Visibility (dashboard-first)

The goal is to run this from the admin dashboard, leaning on code less over time. Widgets
exist (`DataQualityWidget`, `ContactTierWidget`, `PendingReviewWidget`) but show only a
point-in-time snapshot and point at a dormant queue (**gap**). Target:

- A **Data Health page** grouping: completeness, duplicate-rate, conflict count, stub-named
  clients, institutions/shared lines, suppressed-in-export, review-queue depth.
- **Trends over time** — a periodic `data_quality_snapshots` row feeding line charts, so drift
  is visible, not just the current value.
- **Import detail** — per-batch funnel: rows ingested → matched → new contacts → new people →
  sent-to-review → rejected.
- **Review queue UI** as the primary place a human resolves ambiguity (§6).
- **Person & contact pages** showing lineage (which source set which value, when) — extends
  the DNC "why they opted out" detail pages we already built.

---

## 11. Implementation phases

A clean re-import of contacts + campaign results is acceptable, so we build the target model
and reprocess rather than migrate in place.

- **Phase 0 — this spec.** ✅ Done. Lock the rules. *(this document)*
- **Phase 1 — canonical core services + tests.** ✅ Done.
  - `App\Support\Identity\NameClassifier` (§5) — stub/institution/real, strengthened for
    repeated-word and leaked-label stubs; `RawContactImportEnricher::isStubName` delegates to it.
  - `App\Support\Identity\SourceTrust` + `Survivorship` (§3) — trust ranking + winning-value
    resolution with alternates; `resolveName` enforces "a real name never loses to a stub".
  - `App\Support\Identity\PhoneNormalizer` (§7) — the canonical normalizer; parity-tested
    against the legacy IVR normalizer on valid UAE numbers. `WhatsAppPhoneNormalizer` now
    delegates to it. **Remaining:** the IVR normalizer is still independent (it's `@deprecated`);
    its cutover is folded into Phase 2 because it changes import *acceptance*.
  - All pure logic, 64 unit tests, no behaviour change to live imports yet.
- **Phase 2 — one enforced import pipeline.** 🚧 In progress.
  - ✅ The shared resolver (`RawContactImportEnricher::resolveClient`) now implements §4: phone
    match first; stub → fresh; institution → single shared record; real personal name on a new
    phone → fresh (no name-tuple merge). This fixes the WhatsApp and staging paths (IMP-002).
  - ⏳ The IVR **bulk** path (`RawImportProcessor::resolveClients` via `clientKey`) still merges
    real names by tuple. Its rewrite needs a performance-preserving phone-grouped create (the
    current code bulk-inserts clients keyed by name; per-phone fresh clients can't be bulk-loaded
    back by a client attribute), and must be verified against a large dataset before it ships.
  - ⏳ Route **all** imports through staging, idempotent/replayable; remove remaining bypass.
  - ⏳ IVR phone-normalizer cutover to the canonical normalizer (see Phase 1).
- **Phase 3 — live review queue + UI.** Route identity ambiguity (§6) into the existing queue;
  build the side-by-side resolution actions. *(visual)*
- **Phase 4 — Data Health dashboard + snapshots.** Trends, funnels, alerts (§10). *(visual)*
- **Phase 5 — export invariants centralized.** One export service enforcing §8.
- **Phase 6 — reprocess live data.** Re-import into the new model; work the review queue;
  confirm the audit (`clients:audit-data-quality`) reports clean.

Each phase ends with the relevant regression tests and, where it closes an incident, an
`IMP-`/`EXP-` registry entry.

---

## 12. Open decisions (need sign-off)

- The **source trust ranking** in §3 — confirm the order and which sources count as
  "verified".
- The **strong-signal** list for linking multiple numbers to one person (§4 step 5) — which
  identifiers do we actually have (email? Emirates ID? CRM lead-id?).
- Whether a **separate `persons` table** is worth introducing, or we keep `clients` as the
  person and add the confidence/linking metadata to it (cheaper, less disruptive).
