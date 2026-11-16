# Operational completion gap map

Last reconciled: 14 October 2026

The 54-page inventory consists of 13 public pages, 5 authentication/account-lifecycle pages, 9 member pages and 27 administrative route patterns. The earlier “49” total counted only public, member and administrative views and omitted the five authentication/account-lifecycle pages.

## Implemented and tested locally

| Area | Operational evidence |
|---|---|
| Public application | Public submission, Turnstile verification, server validation, normalised duplicate detection, 1980–1982 inclusive screening, flagged exception review and private tokenised tracking pass. |
| Application administration | Review, Request Information, Approve and Reject persist actor/time/notes; approval creates one member/number/invitation atomically and replay is denied. |
| Existing members | Excel-compatible CSV mapping/preview, row conflicts, database duplicates, cancellation, atomic commit, replay protection and zero-account creation pass. The actual client package checksum and 86-row private dry-run reconcile; every production-approval flag remains `NO`. |
| Account lifecycle | Approved/imported-member activation, Better Auth sign-in, administrator-assisted recovery, reset, token expiry/replay controls and suspension/reactivation pass locally and in isolated owner-only staging. |
| Access control | Ordinary members are denied administration/finance/publishing operations; audited role grant/revoke and last-system-admin protection pass. |
| Assessments | Category creation, member targeting, preview, integer-kobo posting, obligation creation, duplicate prevention and idempotent replay pass. |
| Payments | Bank/cash recording, duplicate hint, verification, multi-obligation model, partial allocation, overpayment credit, concurrent idempotency, full reversal and printable receipt pass. |
| Adjustments and reports | Immutable waiver/correction records, allocation-floor safeguard, member statements, aggregate reconciled report and permissioned CSV export are operational. |
| Historical finance | Historical transactions and approved opening balances have separate parsers, previews, origin labels, atomic commits and audit records. Opening balances are never described as reconstructed history. |
| Publishing | Authorised draft/create/edit/publish/archive works for articles, announcements, events, activities and versioned public content. Published records are public; archived articles are excluded. About, leadership, privacy and terms can consume approved `content_pages` records without changing the retained homepage. |
| Recovery | Local AES-256-GCM/scrypt protected export, authenticated metadata/manifest, tamper and wrong-password rejection, plaintext cleanup and restore to an isolated migrated 48-table database pass using synthetic data. Legacy CBC restore remains compatibility-only. |

## Implemented but still requiring staging acceptance

- Authentication hosting/architecture decision after the isolated benchmark measured 89–174ms CPU for secure password work, ruling out Workers Free's 10ms allowance.
- Production-hostname Turnstile behavior and failure modes.
- Responsive acceptance across all operational forms and tables; only the application form has recorded two-width acceptance.
- Keyboard, screen-reader, contrast, CSP, performance and representative load testing.
- Remote D1 export/restore, external custody, retention and recovery-time measurement.
- Installation/verification of the canonical financial invariant triggers in the selected production migration path; the Sites staging migration service cannot parse their bodies.
- Association acceptance of activation/recovery identity verification and applicant notification delivery procedure.

## Deferred by policy or missing approved information

- Real personal/financial import: prohibited until authorised source records, privacy handling and staging acceptance exist. The client package is available outside the repository and its 86 names pass a private dry-run, but all approval flags are `NO` and no financial rows were supplied.
- Automated email/SMS/payment gateway: not approved and not required by the current low-cost design.
- Production Cloudflare project, D1, credentials, domain/DNS and cutover: require association-owned accounts and explicit deployment approval.
- Official leadership profiles, dates, office holders, legal copy and contact details: client drafts exist but remain unverified and unpublished.
- Assessment/payment list filtering and broader usability refinements remain; member search/export, category edit/status, enquiry assignment/resolution, settings mutation, report filtering/export and audit filtering/export are implemented.

## Release conclusion

The mandatory core workflows are operational and test-backed with local synthetic data, and the account lifecycle has isolated Worker evidence. The repository is not production-ready and has not modified Netlify or imported real records. Production remains blocked by the authentication hosting decision, canonical finance migration path, governance, content, credentials, accessibility/security and remote-recovery acceptance—not by placeholder membership or finance pages.
