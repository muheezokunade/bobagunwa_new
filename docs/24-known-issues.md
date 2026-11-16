# Known issues and limitations

## Release-blocking for staging / production (external)

1. **No hosting access** from the build environment: Namecheap staging compatibility, PHP 8.3 web execution, cron, ModSecurity behaviour and SSL are **unverified on the server** (`16-namecheap-hosting-compatibility.md`).
2. **Staging SSL certificate** for `v2-staging.bobagunwa.com` was not issued at the last recorded check; secure login must not be tested over an invalid certificate.
3. **Authorisations and policies** in `23-client-decisions.md` (deployment approvals, number format, roles, amounts, custodians, legal review, content).
4. **Off-site backup custody** not yet established (no custodian holds a key).

## Engineering limitations (accepted, documented)

* Direct-SQL `REPEATABLE READ` stale-snapshot edge case in allocation totals — requires database credentials; detected daily (`05-database-integrity-safeguards.md`).
* PDF active-content screening inspects the raw file; content hidden inside compressed object streams cannot be fully inspected. Evidence and documents are only ever downloaded (never rendered in the browser) and are restricted to officers/members.
* No automated allocation priority, refunds, partial payment reversal or accounting-period closing (policies pending).
* No live payment gateway, SMS or push notifications (not approved; optional SMTP only).
* Reports page renders all member balances (≈260 ms at 1,000 members locally); paginate if membership grows well beyond 1,000.
* Event and member search use `LIKE` on indexed columns; adequate for ≤ 5,000 members.
* The V2 Laravel repository and the local `/Users/demladzico/...` folders were not available to this build, so no V2 code or private client package (86-name roster, leadership rows, draft history) was reviewed directly; their content is known only through V1 documentation.
* The live Netlify site could not be viewed (network policy); the design was reconstructed from V1 source (CSS, markup, crest).
* Browser date inputs show the visitor's locale format (e.g. mm/dd/yyyy in US-configured browsers); the stored value is always ISO.

* No form to add a single member by hand; use a one-row import. An import row whose normalised name matches an existing member is always treated as a duplicate (add a distinguishing middle name or initial).
* With default roles, a historical financial import needs two people holding the Treasurer role (uploader and confirmer both need `finance.import`) when the two-officer rule is on — policy D-007.
* Event publication needs only `events.manage` (not `content.publish`) by design, so the events team can publish its own events.
* A payment refused by a duplicate check after its evidence file was uploaded leaves an unreferenced private file (harmless; not served).
* Period boundaries for "reversals in period" use UTC midnight while other dates are calendar dates (≤ 1 hour difference at the period edges, Lagos is UTC+1).

## Manual checks outstanding

Screen reader, real devices, Firefox/Safari, printed statements, Lighthouse on the final origin, legal review.
