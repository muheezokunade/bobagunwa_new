# Known issues and limitations

## Release-blocking for staging / production (external)

1. **No hosting access** from the build environment: Namecheap staging compatibility, PHP 8.3 web execution, cron, ModSecurity behaviour and SSL are **unverified on the server** (`16-namecheap-hosting-compatibility.md`).
2. ~~**Staging SSL certificate**~~ — **resolved 18 Nov 2026**: valid certificate for `v2-staging.bobagunwa.com` (Namecheap SSL 36817201). It does not renew automatically (no AutoSSL on this account); renew or reissue before 4 Jun 2027.
3. **Authorisations and policies** in `23-client-decisions.md` (deployment approvals, number format, roles, amounts, custodians, legal review, content).
4. **Off-site backup custody** not yet established (no custodian holds a key).
5. **Staging evidence is simulated.** Deployment, preflight, smoke, acceptance (19/19) and the restore drill were run in a simulated cPanel account, not on Namecheap. ModSecurity, LiteSpeed/Apache behaviour, the real PHP 8.3 web SAPI and cron are unverified until the real deployment (`37-staging-deployment-evidence.md`).

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

* With default roles, a historical financial import needs two people holding the Treasurer role (uploader and confirmer both need `finance.import`) when the two-officer rule is on — policy D-007.
* Event publication needs only `events.manage` (not `content.publish`) by design, so the events team can publish its own events.
* One-time links: the first request line containing the token is written to the web server's access log, and the link shown to the issuing officer passes through the encrypted session flash for one request (accepted; `33-one-time-link-assessment.md`).
* Two-factor enforcement is implemented but **off** pending D-024; enrolment shows a manual key and `otpauth://` link (no QR image, to avoid a new dependency).
* Alert email delivery is recorded as `accepted_by_mail_server`, which is not proof of inbox delivery; it is unverified on the hosting until the association configures the existing mailbox (D-025). The dashboard banner is the guaranteed channel.
* Test-reference detection in the generated route catalogue (`docs/35`) counts a route as tested when a feature test calls it by name, including inside a loop over quoted route names; it does not judge the depth of each test. Depth for admin mutations is shown in `docs/34`.
* Rejected-payment evidence files are kept deliberately (they document why the payment was rejected); only files of refused submissions and orphans older than a day are removed (`boba:prune-orphan-files`, daily 03:00).

## Manual checks outstanding

Screen reader, real devices, Firefox/Safari, printed statements on paper (print layout verified in Chromium print emulation only), Lighthouse on the final origin, legal review, independent penetration test.
