# Production release checklist

Production is **not** authorised. Every box must be ticked and evidenced before cutover.

## Approvals (see `29-approval-packet.md`)
- [ ] D-017 staging deployment approved, completed and accepted by officers
- [ ] D-018 production cutover approved (date, domain route, WordPress coexistence)
- [ ] D-004 number format recorded in Settings → Launch approvals
- [ ] D-005/D-007/D-008/D-022 named officers and roles approved
- [ ] D-006 dues/levy categories and amounts approved
- [ ] D-013 delivery channel and identity-verification procedure approved
- [ ] D-014 two backup custodians named; off-site location agreed
- [ ] D-015 public content approved; contact details confirmed
- [ ] D-016 privacy notice and terms legally reviewed and published
- [ ] D-019 member roster verified (if importing) and `approval.member_import` recorded
- [ ] D-020 historical records approved (if importing) and `approval.historical_finance` recorded

## Technical
- [ ] `php artisan boba:preflight` passes on the production host (output filed)
- [ ] Valid TLS certificate for the production hostname; HTTPS redirect enabled; `SESSION_SECURE_COOKIE=true`; `BOBA_FORCE_HTTPS=true`
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, unique `APP_KEY`, dedicated database and user
- [ ] Release built from a tagged revision; checksum verified on the server
- [ ] `migrate --force`, `boba:ledger:verify` clean, caches built
- [ ] Cron `schedule:run` installed and observed running
- [ ] Production backup public key configured; first backup created; **restore drill passed** into an isolated database
- [ ] First administrator provisioned with `boba:admin:create`; no synthetic accounts or data present (`SELECT COUNT(*) FROM users WHERE email LIKE '%@example.test'` = 0)
- [ ] Full test suite green on the release revision; browser audit green
- [ ] Rollback rehearsed on staging (directory switch and database restore)
- [ ] Previous website (WordPress/Netlify) preserved and restorable

## Cutover and acceptance
- [ ] Maintenance window communicated
- [ ] Smoke test on production (public pages, application, login, one officer action per role)
- [ ] Officers' acceptance recorded
- [ ] `28-release-readiness-report.md` updated to "Production accepted" with date and evidence
