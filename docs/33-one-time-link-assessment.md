# One-time link exposure assessment

Activation and password-reset links carry a 32-byte random token in the URL. This assessment covers where that token can appear and what was changed. It was carried out before the mechanism was modified, as the brief required. The security properties of the tokens themselves did not change: they are single-use, expire (activation 7 days, reset 60 minutes), revoke earlier unused links, and are stored only as SHA-256 hashes.

Tests: `AccountLifecycleTest` (clean-URL hand-off, session token, single use, expiry, revocation, denylisted password), `ApplicationWorkflowTest` (raw tokens never in messages, audit or logs), `AdminMutationBehaviourTest::test_member_access_link_and_officer_access_link`.

## Exposure points

| Exposure | Before | Now | Residual risk |
|---|---|---|---|
| Browser cache / proxies | `Cache-Control: no-store, private` on `account/*` | unchanged | none known |
| Browser history and address bar | full token URL remained while the form was open and after submission | `GET /account/activate/{token}` and `/account/reset/{token}` move the token into the encrypted session and **redirect at once** to `/account/activate` or `/account/reset`; the form posts the session-held token, which is forgotten after use | the original URL remains as one history entry (single-use, so useless once used or expired) |
| `Referer` header to other sites | default policy (`strict-origin-when-cross-origin` sends origin only) | `Referrer-Policy: no-referrer` on every `account/*` response; no third-party resources exist (strict CSP) | none known |
| Web-server access log | first GET logged with token | unchanged — the token is in the first request line and **cannot** be kept out of Apache/LiteSpeed access logs without breaking normal links | accepted: logs are on the private hosting account; the token is single-use, short-lived for resets, and only its hash is in the database |
| Application logs, audit, messages | not written (tested) | unchanged | none |
| Officer screen (link shown to issuing officer) | carried through the **encrypted** database session flash for one request | unchanged; shown once with a copy button; page is `no-store` | accepted: session payload is encrypted with `APP_KEY` and deleted after display. Showing the link without a redirect would break the post/redirect/get pattern that prevents duplicate issuance on refresh. |
| Email (only if SMTP delivery is enabled) | link in body | unchanged | email is outside the platform's control; enable only if the association accepts that (D-013) |

## Conclusion

The token is now present in the browser only for one redirect. It is never sent as a referrer, never cached, never logged by the application, and never stored in plain text. The remaining exposures (one access-log line, an encrypted one-request session flash, and the optional email) are accepted. They are documented in [24-known-issues.md](24-known-issues.md).
