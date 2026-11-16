# Integrity alerting and escalation

Problems found by the automated integrity checks must reach the people responsible. They must never be dismissed silently, and they must never leak financial details. This document describes what raises an alert, who sees it, how delivery is recorded, and how an alert is closed.

Code: `App\Services\IntegrityMonitor`, `App\Http\Controllers\Admin\AlertController`, `resources/views/partials/integrity-banner.blade.php`. Tests: `tests/Feature/Monitoring/IntegrityAlertTest.php`. Routes: `admin.alerts.*` in [35-route-catalogue.md](35-route-catalogue.md).

## What raises an alert

| Alert type | Severity | Raised when | Source |
|---|---|---|---|
| `integrity_triggers_missing` | critical | One or more of the 35 database integrity triggers is absent | `boba:ledger:verify` (daily 01:30), **Run check now**, restore verification |
| `ledger_reconciliation` | critical | `LedgerQuery::exceptions()` reports any reconciliation exception (e.g. over-allocation, orphaned allocation, status mismatch) | same |
| `integrity_check_error` | critical | The check itself throws (database unreachable, query failure) | same |
| `integrity_check_overdue` | warning | No completed check for more than **36 hours** (`OVERDUE_HOURS`), so a broken cron cannot hide failures | hourly scheduler entry `boba:integrity-overdue`, and on admin page views (at most every 10 minutes) |

Every run is stored in `integrity_check_runs` (trigger, status passed/failed/error, missing triggers, exception **counts**, who requested it, start/finish). Runs are append-only (database triggers).

**One alert per condition.** Each alert has a fingerprint (type plus which checks failed). A unique key on a generated `open_fingerprint` column allows only one unresolved alert per fingerprint. A recurrence increments `occurrences` and `last_detected_at` rather than creating duplicates. If a condition recurs after its alert was resolved, a **new** alert is raised.

## Who sees it

Officers holding `integrity.alerts`: System Administrator, Treasurer, Payment Verifier, Executive (see [15-roles-and-permissions.md](15-roles-and-permissions.md)).

* **Banner on every administration page.** It cannot be dismissed. It lists open and acknowledged alerts, critical first, and stays until the alert is resolved.
* **Receipts:** the first time an alert is shown to an officer, an `alert_receipts` row records who saw it and when. This is the evidence that the alert was *exposed* to that officer in the dashboard.
* **No treasury data through alerts.** Alert pages and banners show the alert type, severity, timestamps and exception **counts** only: no member names, amounts, payment references or rows. A System Administrator can see that reconciliation failed but still gets 403 on every finance route (tested).

## Email (optional, existing mailbox only, free)

Email is used only when **all** of these hold:

1. Settings → Alerts → “Also email integrity alerts” is on (`alerts.email_enabled`, default off);
2. the channel has been **verified**: an administrator with `settings.manage` sends a test from Integrity alerts, receives a six-digit code at their own address, and enters it (the code is stored hashed in the cache for 30 minutes);
3. the mailer is a real transport (not `log` or `array`).

Delivery is recorded per recipient in `alert_notifications`, which is append-only, with an honest status:

| Status | Meaning |
|---|---|
| `not_configured` | Email is off or unverified; nothing was sent. The officer is reached only by the dashboard banner. |
| `accepted_by_mail_server` | The SMTP server accepted the message. This is **not** proof that it reached the inbox. |
| `failed` | The send threw; the error class and message (≤ 250 chars, no secrets) are stored. |

The email body is deliberately generic: alert number and severity only, plus an instruction to sign in. It carries no amounts, names, references or check details (`IntegrityMonitor::emailBody`; tested).

**Reminders.** While an alert is unresolved, every further detection re-notifies recipients at most once every **24 hours** (`REMINDER_HOURS`).

Nobody is reported as “notified” unless a row shows `accepted_by_mail_server` or a receipt shows the banner was displayed to them.

## Acknowledgement and resolution

| Step | Who | Rule |
|---|---|---|
| Acknowledge | any `integrity.alerts` holder | Only an **open** alert; a note of at least 10 characters (who is investigating). The banner stays visible. |
| Resolve | any `integrity.alerts` holder | Only after a later **passing** check has set `condition_cleared_at`. A resolution note is required. An alert whose condition is still present cannot be closed. |

Alerts are never deleted (database trigger). Raising, acknowledging, resolving and channel verification are audited.

## Escalation procedure (also shown on each alert page)

1. Do not edit, delete or “balance” financial records.
2. Acknowledge the alert, saying who is investigating.
3. Inform the Treasurer and the Executive the same day, and escalate to the technical maintainer.
4. The maintainer finds the cause ([05-database-integrity-safeguards.md](05-database-integrity-safeguards.md)). Corrections are made only through adjustments or reversals; missing triggers are reinstalled by re-running the migration that installs them.
5. Run the check again. Once it passes, resolve the alert with a note of the cause and the fix.

## What is not done

* No SMS, push or paid alerting service (no recurring cost).
* No automatic correction of anything.
* On staging, email delivery remains unverified until the association chooses to configure the existing mailbox. The dashboard banner is the guaranteed channel.
