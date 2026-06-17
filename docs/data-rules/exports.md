# Export edge cases (`EXP-`)

See [README.md](README.md) for how this registry works. Newest entries at the top.

<!--
### EXP-000 — <short title>
- **Date / trigger:** <when, and what made us look>
- **Symptom:** <what the exported file/data looked like>
- **Root cause:** <the underlying mechanism>
- **Rule we added:** <what the code now does>
- **Why it's safe / trade-offs:** <what we accept in exchange>
- **Proof:** <test name>, commit <sha>
- **Code:** <file:line of the guard, carries the `EXP-000` breadcrumb>
- **Watch out for:** <known gaps / counter-examples>
-->

### EXP-001 — WhatsApp Numbers export must reuse the table's filtered query

- **Date / trigger:** 2026-06-17. Filtering WhatsApp Numbers by **active + tag "Paul Database"**
  showed **7,066** rows in the table but the export produced a sample drawn from **~76k**.
- **Symptom:** The exported CSV contained mostly numbers that did **not** match the on-screen
  filters — for the report above, ~92% of the eligible export pool was not in the selected tag.
- **Root cause:** The "Export Filtered CSV" action
  ([`ListWhatsAppNumbers`](../../app/Filament/Resources/WhatsAppNumbers/Pages/ListWhatsAppNumbers.php))
  rebuilt its own query from `getEloquentQuery()` and **re-implemented filters by hand**,
  applying only 6 of the table's 10 filters. It silently dropped **tags, uae_only, is_lead,
  suppressed**, and defined `wa_status=active` differently from the table. Classic filter drift:
  filters added to the table were never mirrored into the export.
- **Rule we added:** The export now starts from **`$this->getFilteredTableQuery()`** — the exact
  query the table is showing — so every current and future table filter applies automatically.
  Only export-specific logic is layered on top: the exclude-previous-batches subquery and a hard
  compliance guard that always drops active WhatsApp suppressions. The old `applyActiveConditions`
  /`activeExportQuery` helpers (which forced active-only and were the source of the divergent
  definition) were removed.
- **Why it's safe / trade-offs:** The export now mirrors the table exactly (minus suppressed),
  which is the expected behaviour. Behaviour change worth noting: the export no longer *forces*
  active-only — if you filter the table by `cooldown`/`dead`, those now export (the table is the
  source of truth). Unsubscribed numbers are still never exported.
- **Proof:** `WhatsAppNumberExportTest::imp_exp_001_export_respects_the_tag_filter` and
  `::imp_exp_001_export_never_includes_suppressed_numbers`. Verified against Postgres: active +
  tag + uae query = 7,066, matching the table.
- **Code:** the `EXP-001` breadcrumbs in `ListWhatsAppNumbers::getHeaderActions()` (the
  `getFilteredTableQuery()` call and the suppression guard).
- **Watch out for:**
  - Any future export action should reuse `getFilteredTableQuery()` rather than rebuild a query —
    that's the whole lesson here. The IVR numbers export uses a different (route-based) path; if a
    similar tag/filter is added there, check it too.
