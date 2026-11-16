# Source reference inventory

| Source | Availability in this build | Used for | Authority |
|---|---|---|---|
| Execution brief (16 Nov 2026) | Available | Scope, constraints, acceptance criteria | Highest |
| V1 repository `muheezokunade/bobagunwa` @ `945ffb5` | Cloned read-only | Requirements, eligibility policy, finance rules, permission catalogue, design (CSS, crest, copy), decision register, acceptance scenarios | Confirmed business rules; implementation superseded |
| V1 docs (copied to `historical/v1/`) | Available | Historical reference only | Not evidence for this build |
| V2 Laravel repository `new_bobagunwa` | **Not available** (not on GitHub for this account; local paths absent) | — | — |
| Local folders `/Users/demladzico/...` | **Not available** in this environment | — | — |
| Client data package (86 names, 43 leadership rows, drafts) | **Not available**; known via V1 `client-content-migration-plan.md` | Scope of import and content decisions | Unverified client material |
| Live Netlify site | **Blocked** by network policy | Design reconstructed from V1 source, which V1's audit states matches the deployment | — |
| Namecheap hosting | **No access** | Historical facts from the brief only | Must be re-verified |

## Reused, corrected or superseded

| Item | Treatment |
|---|---|
| Eligibility 1980-01-01 – 1982-12-31 inclusive, flag-not-reject, policy 2026-10-11-v1 | **Reused** as confirmed policy |
| Application states, private notes vs applicant messages, reference + secret token, 90-day expiry | **Reused** |
| `BOB-######` numbers, never reused | **Reused** as provisional, gated pending approval |
| 19 V1 permissions | **Extended** to 24 (separate verify/allocate/adjust/reverse/import, accounts.issue, members.export, content.publish) |
| V1 `system_admin` held **all** permissions | **Corrected**: system administrator has no treasury or approval powers (brief §16) |
| Two SQLite finance triggers | **Superseded** by 30 MariaDB triggers with row locking and composite keys |
| Integer kobo, derived balances, linked reversals, opening-balance vs historical models | **Reused** |
| Better Auth / Workers / D1 / Turnstile | **Not ported** (brief §3); replaced by Laravel auth, honeypot + timing + rate limits |
| AES-256-GCM/scrypt passphrase backups | **Superseded** by libsodium sealed-box + secretstream (no secret on server) |
| V1 homepage copy and values | **Reused** with orthography correction pending approval; fabricated "impact" tiles and placeholders **removed** |
| Contact email conflict, phone, address | **Not published** until confirmed |
| Seven mandatory workflows | **Reused** as acceptance specification; reimplemented as `MandatoryWorkflowsTest` |
