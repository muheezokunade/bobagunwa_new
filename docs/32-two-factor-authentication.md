# Two-factor authentication (TOTP) for officers

**Status: implemented and tested; enforcement OFF.** Do not turn on enforcement for real officers until the association approves it (decision D-024). Before enabling it, every privileged officer must have enrolled, stored their recovery codes, and rehearsed a lost-device reset on staging.

Code: `App\Support\Totp`, `App\Services\TwoFactorService`, `Auth\TwoFactorChallengeController`, `Admin\AccountController`, `EnsureTwoFactorEnrolled` middleware. Tests: `tests/Feature/Auth/TwoFactorTest.php`, `RemainingRoutesTest::test_recovery_code_regeneration_requires_current_code`.

## Design

* **Standard:** RFC 6238 TOTP (HMAC-SHA-1, 6 digits, 30-second step), compatible with free authenticator apps (Google Authenticator, Microsoft Authenticator, Aegis, 2FAS, FreeOTP). Verified against the RFC 6238 Appendix B test vectors.
* **No new dependency or service.** No QR-code library is bundled. Enrolment shows the secret as a manual-entry key plus an `otpauth://` link, which opens the authenticator app directly on a phone.
* **Clock tolerance:** ±1 step (±30 s).
* **Replay prevention:** the last accepted time step is stored (`two_factor_last_step`) under a row lock, so a code cannot be used twice, even by two concurrent requests.
* **Secrets at rest:** the TOTP secret is encrypted with `APP_KEY` (`encrypted` cast). It is never logged, audited or shown after enrolment.
* **Recovery codes:** 8 single-use codes, shown once, stored as SHA-256 hashes. A used code is removed. They can be regenerated with a current authenticator code; the old codes then stop working.

## Who it applies to

Opt-in for every officer: Administration → My account → Two-factor sign-in.

When **Settings → Security → Require two-factor sign-in for privileged officers** is on (`security.require_two_factor`), officers holding any of `access.manage`, `settings.manage`, `backups.manage`, `finance.assessments.manage`, `finance.payments.record`, `finance.payments.verify`, `finance.allocate`, `finance.adjust`, `finance.reverse` or `finance.import` are redirected to My account until they enrol. Members are never forced.

## Sign-in flow

1. Email and password are checked as before (generic errors, rate limits).
2. If the account has confirmed two-factor, the user is **not** signed in yet. A challenge record (user, 5-minute expiry, attempt count) is placed in the session, and the user is sent to `/login/two-factor`.
3. A correct TOTP code or an unused recovery code completes sign-in (session regenerated). Five wrong codes, or expiry, discard the challenge, and the user must start again. The route is also rate limited (`throttle:two-factor`).

## Lockout prevention

* Enforcement **cannot be switched on** while any active privileged officer has not enrolled. The settings form names the reason and refuses.
* An officer cannot disable their own two-factor while enforcement applies to them. Disabling always needs the password **and** a current code.
* **Lost device:** an officer with `access.manage` can reset another officer's two-factor (Officers and access → the officer → Reset two-factor). This needs a reason and an identity-confirmation tick. It never works on one's own account, and it ends all of the target's sessions. The officer then re-enrols at next sign-in.
* **Last administrator** who loses both device and recovery codes: a maintainer with SSH access clears the four `two_factor_*` columns for that user with `php artisan tinker` and records the action in the audit register. This is the documented break-glass path; it requires server access and is not reachable from the web.

## Audit

Enrolment, disable, admin reset, recovery-code use and regeneration, and rejected replays are recorded in `security_events`/`audit_events` without secrets or codes.

## Rollout steps (after approval D-024)

1. On staging, each privileged officer enrols and stores the recovery codes offline (printed, kept privately).
2. Rehearse one lost-device reset by a second administrator.
3. The System Administrator switches on enforcement; the form refuses if anyone is missing.
4. Record the date in `23-client-decisions.md`.
