# Import edge cases (`IMP-`)

See [README.md](README.md) for how this registry works. Newest entries at the top.

---

<!--
### IMP-000 — <short title>
- **Date / trigger:** <when, and what made us look>
- **Symptom:** <what the user/data looked like>
- **Root cause:** <the underlying mechanism>
- **Rule we added:** <what the code now does>
- **Why it's safe / trade-offs:** <what we accept in exchange>
- **Proof:** <test name>, commit <sha>
- **Code:** <file:line of the guard, carries the `IMP-000` breadcrumb>
- **Watch out for:** <known gaps / counter-examples>
-->

### IMP-001 — Stub/placeholder names must not merge distinct leads

- **Date / trigger:** 2026-06-17. Investigating client **496904 "✅ Instagram Dm |"**,
  which had **9 phone numbers** — 8 unrelated Iranian (+98…) numbers all added in one
  batch from source *"Dafala June 12th 2026"* (2026-06-13 07:56:53), each flagged
  `duplicate=false` (genuinely new numbers) with `raw_name = "✅ Instagram Dm |"`.
- **Symptom:** One client accumulated phone numbers belonging to many different people,
  all from a single import. The displayed contact is a placeholder, not a real person.
- **Root cause:** The WhatsApp raw importer resolves the client for a *new* phone number via
  `RawContactImportEnricher::resolveClient()`, which used
  `Client::firstOrCreate(['full_name', 'emirate', 'country_iso'])`. The phone number is **not**
  part of that identity. When the name is a generic placeholder ("✅ Instagram Dm |") with
  blank emirate/country, the identity tuple collapses to `(stub, null, null)` and collides
  across unrelated rows — so every new number with that name was piled onto the same client.
  (28 clients shared that exact tuple, and `firstOrCreate(...)->first()` has no deterministic
  order, so *which* client wins was arbitrary too.)
- **Rule we added:** In `resolveClient()`, when `self::isStubName($fullName)` is true, skip the
  tuple match and **`Client::create()` a fresh client** for that phone. This mirrors the IVR
  path (`RawImportProcessor`), which already had this guard — the WhatsApp/shared-enricher path
  was simply missing it. Note `"instagram dm"` was already in `isStubName`'s placeholder list;
  the detector knew it was a stub, but `resolveClient` never consulted it.
- **Why it's safe / trade-offs:** Real names still merge on the identity tuple (so legitimate
  repeat contacts aren't fragmented). Re-imports of the *same* phone still match by phone first
  (the stub branch is only reached for brand-new numbers). Trade-off: stub-named rows may create
  more thin client records — acceptable, since merging strangers is far worse than a duplicate
  placeholder client.
- **Proof:** `RawContactImportEnrichmentTest::imp_001_stub_named_rows_never_merge_distinct_leads`
  and `::imp_001_real_named_rows_still_merge_on_the_identity_tuple`.
- **Code:** [`app/Support/RawContactImportEnricher.php`](../../app/Support/RawContactImportEnricher.php)
  — the `IMP-001` guard in `resolveClient()`. Detector: `RawContactImportEnricher::isStubName()`.
- **Cleanup (historical residue):** 2026-06-17, ran `clients:split-name-collisions --threshold=5
  --stub-only --apply` (the `--stub-only` flag was added for this — it restricts the splitter to
  placeholder-named clients so banks/shared lines/real repeat contacts are left alone). Split
  **171 stub-named clients holding 1,023 phone numbers** into one client per phone. 0 numbers
  lost (all reassigned, 0 placeholder deletions); each split client was snapshotted to
  `client_audit_logs` (action `split`) first, so it is reversible. After: 0 stub-named clients
  with ≥5 phones; the 52 non-stub high-volume clients were untouched.
- **Detector:** `clients:audit-data-quality --phone-threshold=N` flags this pattern; scheduled
  weekly. Re-running it should now report 0.
- **Watch out for:**
  - Two genuinely-same people who both arrive with only a stub name will **not** be merged —
    an accepted gap (we'd rather under-merge than merge strangers).
  - `isStubName()` is heuristic. Labels seen slipping past it during cleanup — **"Missed Call",
    "Pflead Pflead", "Whatsapp From Bayut"** — should be added to `PLACEHOLDER_LABEL_FRAGMENTS`
    so both the import guard and the detector catch them.
  - The detector query is capped at `LIMIT 500` candidates before the stub filter; fine while
    well under 500 clients have ≥`phone-threshold` numbers (223 at the time of writing), but
    revisit if that grows.
