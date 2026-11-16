# Staging-readiness report

Date: 15 October 2026
Conclusion: ready for an authorised staging deployment after the external setup items below; not approved for production

## 1. Completed and tested locally

- 54 page routes are present, navigable by their intended role and deliberately remain Partial.
- 28 unit tests, the 48-table schema check, database invariants, typecheck, lint and the production build pass.
- Public, authenticated member and administrative live suites pass. The 54-route visible-copy audit passes.
- The seven operational workflows pass together with synthetic data. The financial result reconciles at ₦50,000 assessed, ₦20,000 paid and ₦30,000 outstanding.
- Backup format version 2 uses AES-256-GCM with authenticated metadata, random per-backup nonce/salt and scrypt key derivation. Tamper, wrong-password, truncation, cleanup and integrated restore/reconciliation tests pass.
- Legacy version 1 AES-256-CBC/PBKDF2 restore remains available with an explicit warning. It is not represented as authenticated encryption.
- All 13 native `<img>` warnings were removed by using the framework image component with intrinsic dimensions and responsive sizing. Lint now has zero warnings.
- The CMS content-detail link/query mismatch was fixed and the route was reverified.
- The actual client package checksum register passes; its 86 provisional members parse in dry-run with no generated accounts, IDs or publication.
- All 52 substantive route views pass automated responsive/semantic checks at 320×800, 768×1024 and 1440×900 for public, member and administrator roles. Password/mutation forms now have safe POST fallbacks before hydration, and the homepage no longer overflows at 320px. The two redirect/action routes are covered by the live auth suite.
- `npm audit --omit=dev` reports zero production dependency findings. Eight high development/build findings remain under an unpatched glob-pattern denial-of-service advisory; affected packages are not in the Worker bundle.

Final local verification evidence is recorded in [`evidence/release-hardening-verification-2026-10-14.md`](./evidence/release-hardening-verification-2026-10-14.md) and [`evidence/responsive-accessibility-2026-10-15.md`](./evidence/responsive-accessibility-2026-10-15.md). The complete administrative suite passed after a clean local runtime restart and synthetic fixture reset; local evidence does not substitute for staging concurrency acceptance.

## 2. Tested successfully on Cloudflare staging

Existing evidence from the isolated owner-only Cloudflare Worker confirms activation, password verification, login, recovery, session listing/revocation, suspension-related access enforcement and permission controls. Worker CPU telemetry recorded 89–146 ms for successful password verification, 174 ms for activation hashing and 146 ms for reset hashing. This successfully establishes functionality and also establishes that Workers Free's 10 ms request allowance is unsuitable without weakening password security.

No new Cloudflare staging deployment was performed in this stage because association-controlled access and plan approval were not supplied.

## 3. Blocked by accounts, credentials or association approvals

- Association-controlled Cloudflare project, staging D1, hostname, Turnstile widget/keys and deployment authority.
- Approval for Workers Paid or a secure alternate authentication architecture.
- Backup custody location, custodians, retention and remote D1 restore authorisation.
- Administrator identities/permissions, invitation-delivery procedure and recovery identity-verification procedure.
- Official dues, levy, opening-balance and historical-finance policies/data.
- Approval of the 86-member roster and official membership-number convention.
- Corrected/approved history, welcome, About, leadership terms/names/photos and public-display consent.
- Legal approval for privacy, terms and data-retention wording.

## 4. Outstanding engineering issues

- Automated responsive/semantic checks now pass for all 52 substantive page views at phone, tablet and desktop sizes, while `/portal` and `/portal/logout` pass their live redirect/action checks. Manual keyboard journeys, screen-reader testing, browser-matrix testing and final-origin performance/accessibility acceptance remain staging gates.
- Remote D1 authenticated export, off-account custody and isolated Cloudflare restore are not yet tested.
- Security-header/CSP, Lighthouse, browser-matrix and representative load acceptance remain pending on the canonical HTTPS origin.
- The build-tool advisory has no upstream fixed `braces` release at the time of this audit. Keep CI inputs trusted, do not accept user-controlled glob patterns, monitor upstream releases and repeat both full and production-only audits before staging sign-off.
- Legacy CBC backups should be restored only for migration, then immediately re-exported as version 2. Their checksum does not provide authenticated-encryption guarantees.

The existing Netlify production site, production DNS and production databases were not modified.
