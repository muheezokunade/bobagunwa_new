<?php

namespace App\Support;

/**
 * Hand-written description of every named route. Roles, middleware, security
 * controls, validation fields and test references are derived from the code
 * by `php artisan boba:docs:reference`; only the meaning lives here.
 * tests/Feature/Docs/RouteCatalogueSyncTest fails if a route is missing.
 *
 * Keys: p = purpose, d = data read/written, s = success, f = failure states,
 * a = acceptance criterion. For GET routes s/f default to the read-only states.
 */
final class RouteCatalogue
{
    public const READ_SUCCESS = '200 with the page; nothing is written.';

    public const READ_FAILURE = 'Anonymous → redirect to sign-in; without permission → 403; unknown record → 404.';

    public const PUBLIC_FAILURE = 'Unknown or unpublished record → 404.';

    public static function all(): array
    {
        return [
            // Public website
            'home' => ['p' => 'Public home page with the approved introduction and the latest published news and events.', 'd' => 'pages, posts, events (published only)', 'a' => 'Shows only published, public content; renders without JavaScript.'],
            'about' => ['p' => 'Managed “About” page.', 'd' => 'pages, page_revisions', 'a' => 'Shows the published revision or the approved placeholder; never an unpublished draft.'],
            'history' => ['p' => 'Managed history page.', 'd' => 'pages, page_revisions', 'a' => 'No invented historical facts; unpublished revisions hidden.'],
            'leadership' => ['p' => 'Leadership directory.', 'd' => 'leaders (published only)', 'a' => 'Only approved, published leaders are listed.'],
            'membership' => ['p' => 'Membership information and eligibility rules (birth dates 1980-01-01 to 1982-12-31).', 'd' => 'settings, pages', 'a' => 'States the eligibility range and links to the application form.'],
            'activities' => ['p' => 'Published association activities.', 'd' => 'activities', 'a' => 'Only published activities are shown.'],
            'events.index' => ['p' => 'Public upcoming and past events.', 'd' => 'events (public, published)', 'a' => 'Member-only and draft events never appear.'],
            'events.show' => ['p' => 'Single public event.', 'd' => 'events', 'f' => self::PUBLIC_FAILURE, 'a' => 'Member-only or draft slug returns 404.'],
            'news.index' => ['p' => 'Public news list.', 'd' => 'posts (type news, public, published)', 'a' => 'Drafts and member notices are excluded.'],
            'news.show' => ['p' => 'Single news article.', 'd' => 'posts', 'f' => self::PUBLIC_FAILURE, 'a' => 'Unpublished or member-only slug returns 404.'],
            'gallery.index' => ['p' => 'Published galleries.', 'd' => 'galleries, gallery_items, media', 'a' => 'Only published galleries with rights-confirmed images.'],
            'gallery.show' => ['p' => 'Single gallery.', 'd' => 'galleries, gallery_items, media', 'f' => self::PUBLIC_FAILURE, 'a' => 'Draft gallery slug returns 404; every image has alt text.'],
            'privacy' => ['p' => 'Privacy notice (managed page).', 'd' => 'pages', 'a' => 'Reachable from every page footer.'],
            'terms' => ['p' => 'Terms of use (managed page).', 'd' => 'pages', 'a' => 'Reachable from every page footer.'],
            'sitemap' => ['p' => 'XML sitemap of public pages.', 'd' => 'pages, posts, events, galleries', 's' => '200 XML; nothing is written.', 'a' => 'Contains no admin, portal or member-only URLs.'],
            'robots' => ['p' => 'robots.txt with an absolute Sitemap URL built from APP_URL.', 'd' => 'none', 's' => '200 text; nothing is written.', 'a' => 'Disallows admin, portal, account and status pages.'],
            'contact' => ['p' => 'Contact form.', 'd' => 'settings', 'a' => 'Form carries CSRF token, consent checkbox and honeypot.'],
            'contact.store' => ['p' => 'Submit an enquiry.', 'd' => 'writes enquiries', 's' => 'Redirect with the enquiry reference.', 'f' => 'Validation errors re-shown; honeypot or rate limit → refused without storing.', 'a' => 'One enquiry row with a reference; officers see it in the enquiry queue.'],
            'membership.apply' => ['p' => 'Membership application form.', 'd' => 'settings', 'a' => 'Explains the eligibility range; works without JavaScript.'],
            'membership.apply.store' => ['p' => 'Submit a membership application.', 'd' => 'writes membership_applications, application_events', 's' => 'Redirect to the receipt with a reference and a private status token.', 'f' => 'Validation errors; duplicate open application by email/phone refused; outside the birth-date range → accepted but flagged for human review.', 'a' => 'Never auto-rejects on date of birth; one open application per email/phone.'],
            'membership.receipt' => ['p' => 'One-time receipt showing the reference and status token.', 'd' => 'session flash only', 'f' => 'Without a fresh submission → redirect to the application form.', 'a' => 'Token shown once; page is no-store.'],
            'membership.status' => ['p' => 'Application status lookup form.', 'd' => 'none', 'a' => 'Asks for reference and private token.'],
            'membership.status.lookup' => ['p' => 'Look up an application by reference and token.', 'd' => 'reads membership_applications, application_events', 's' => 'Status and applicant-visible messages only.', 'f' => 'Wrong pair → generic “not found” (no enumeration); rate limited.', 'a' => 'Private officer notes are never shown.'],
            'membership.status.respond' => ['p' => 'Applicant replies to an officer request for information.', 'd' => 'writes application_events', 's' => 'Reply recorded in the timeline.', 'f' => 'Wrong reference/token or application not awaiting information → refused.', 'a' => 'Reply only possible while the application requests information.'],
            'membership.status.respond.redirect' => ['p' => 'GET on the reply address (refresh, back button, bookmark) redirects to the status form instead of a 405.', 'd' => 'none', 's' => '302 to /membership/status.', 'a' => 'A refreshed or bookmarked reply page never resubmits and never shows a 405.'],

            // Authentication
            'login' => ['p' => 'Sign-in form.', 'd' => 'none', 'a' => 'Signed-in users are redirected away.'],
            'login.attempt' => ['p' => 'Password sign-in.', 'd' => 'reads users; writes sessions, security_events', 's' => 'Officer → admin dashboard, member → portal; if two-factor is confirmed → code challenge first.', 'f' => 'Generic error for wrong email/password/inactive account; rate limited; failures logged.', 'a' => 'Session regenerated; suspended or pending accounts cannot sign in.'],
            'login.two-factor' => ['p' => 'Two-factor code challenge after a correct password.', 'd' => 'session', 'f' => 'No pending or an expired challenge → redirect to sign-in.', 'a' => 'User is not authenticated until the code is verified.'],
            'login.two-factor.verify' => ['p' => 'Verify a TOTP or recovery code.', 'd' => 'users (two_factor_last_step, recovery codes), security_events', 's' => 'Signed in; recovery code consumed if used.', 'f' => 'Wrong/replayed code → error; 5 failures or 5-minute expiry → challenge discarded.', 'a' => 'Codes cannot be replayed; recovery codes are single use.'],
            'logout' => ['p' => 'Sign out.', 'd' => 'sessions', 's' => 'Session invalidated; redirect home.', 'f' => 'Anonymous → redirect to sign-in.', 'a' => 'Session token regenerated.'],
            'password.request' => ['p' => 'Account recovery request form.', 'd' => 'none', 'a' => 'Explains that an officer verifies identity before issuing a link.'],
            'password.email' => ['p' => 'Submit a recovery request.', 'd' => 'writes recovery_requests, security_events', 's' => 'Same neutral message whether or not the account exists.', 'f' => 'Rate limited.', 'a' => 'No account enumeration; no email is sent automatically.'],
            'account.activate' => ['p' => 'Entry point of a one-time activation link.', 'd' => 'session', 's' => 'Token moved into the encrypted session; redirect to the clean form URL.', 'f' => 'None at this step (validated on submit).', 'a' => 'Token leaves the address bar and history; Referrer-Policy no-referrer.'],
            'account.activate.form' => ['p' => 'Choose a password for a new account.', 'd' => 'session', 'f' => 'Missing token → the form explains the link is invalid.', 'a' => 'Page is no-store and no-referrer.'],
            'account.activate.store' => ['p' => 'Activate an account with the one-time token.', 'd' => 'account_tokens, users, audit_events', 's' => 'Password set, token consumed, redirect to sign-in.', 'f' => 'Expired, revoked, used or wrong token → refused; weak or denylisted password → validation error; rate limited.', 'a' => 'Token single-use; only its SHA-256 hash is stored.'],
            'password.reset' => ['p' => 'Entry point of a one-time reset link.', 'd' => 'session', 's' => 'Token moved into the session; redirect to the clean form URL.', 'f' => 'None at this step.', 'a' => 'As account.activate.'],
            'password.reset.form' => ['p' => 'Choose a new password.', 'd' => 'session', 'f' => 'Missing token → explanation.', 'a' => 'Page is no-store and no-referrer.'],
            'password.update' => ['p' => 'Reset a password with the one-time token.', 'd' => 'account_tokens, users, sessions, audit_events', 's' => 'Password replaced, other sessions ended, token consumed.', 'f' => 'Invalid token or weak password → refused; rate limited.', 'a' => 'All existing sessions of the account end.'],

            // Member portal
            'portal.dashboard' => ['p' => 'Member home: membership summary, balance, notices, events.', 'd' => 'members, LedgerQuery, posts, events', 'a' => 'Shows only the signed-in member’s records.'],
            'portal.profile' => ['p' => 'View own contact details.', 'd' => 'members', 'a' => 'No other member’s data reachable.'],
            'portal.profile.update' => ['p' => 'Update own contact details (limited fields).', 'd' => 'members, audit_events', 's' => 'Details saved and audited.', 'f' => 'Validation errors; duplicate email/phone refused.', 'a' => 'Name, date of birth and membership number cannot be changed by the member.'],
            'portal.finances' => ['p' => 'Own obligations, payments and balance.', 'd' => 'LedgerQuery (member_obligations, payments, allocations, adjustments)', 'a' => 'Balance equals the derived ledger; no other member visible.'],
            'portal.payments.create' => ['p' => 'Member reports a payment: choose the due or levy (part-payment allowed), date, method, payer name and receipt.', 'd' => 'reads member_obligations for the signed-in member only', 'a' => 'Only the member’s own open dues are offered.'],
            'portal.payments.store' => ['p' => 'Submit the reported payment with its receipt; recorded as pending for a payment verifier.', 'd' => 'writes payments, private_files, audit_events', 's' => 'Pending payment with the member as recorder; a different person must verify it.', 'f' => 'Missing receipt, future date, another member’s levy or duplicate bank reference → refused; replay returns the original.', 'a' => 'Counts towards the balance only after verification; receipt stored privately.'],
            'portal.statement' => ['p' => 'Printable own statement for a period.', 'd' => 'LedgerQuery', 'f' => 'Invalid dates → validation error.', 'a' => 'Period boundaries are Lagos calendar days; print stylesheet hides navigation.'],
            'portal.statement.csv' => ['p' => 'Download own statement as CSV.', 'd' => 'LedgerQuery', 's' => '200 CSV; formula-injection safe.', 'a' => 'Totals match the on-screen statement.'],
            'portal.notices' => ['p' => 'Member-only notices.', 'd' => 'posts (announcements), announcement_reads', 'a' => 'Only published member notices.'],
            'portal.notices.read' => ['p' => 'Mark a notice as read.', 'd' => 'announcement_reads', 's' => 'Read marker stored (idempotent).', 'f' => 'Unpublished notice → 404.', 'a' => 'Repeating the request does not duplicate the marker.'],
            'portal.events' => ['p' => 'Member events list.', 'd' => 'events, event_rsvps', 'a' => 'Includes member-only published events.'],
            'portal.events.show' => ['p' => 'Single member event with RSVP form.', 'd' => 'events, event_rsvps', 'f' => 'Draft event → 404.', 'a' => 'Shows the member’s current response.'],
            'portal.events.rsvp' => ['p' => 'Respond to an event.', 'd' => 'event_rsvps', 's' => 'Response stored or updated (one per member).', 'f' => 'RSVP closed or event unpublished → refused.', 'a' => 'Unique per member per event.'],
            'portal.documents' => ['p' => 'Member-only documents.', 'd' => 'documents', 'a' => 'Only published documents.'],
            'portal.documents.download' => ['p' => 'Download a member document.', 'd' => 'documents, private_files', 's' => 'File streamed from outside the webroot.', 'f' => 'Unpublished → 404.', 'a' => 'Files are never publicly addressable.'],
            'portal.security' => ['p' => 'Password and session management.', 'd' => 'sessions', 'a' => 'Lists the member’s own sessions only.'],
            'portal.security.password' => ['p' => 'Change own password.', 'd' => 'users, sessions, security_events', 's' => 'Password changed; other sessions ended.', 'f' => 'Wrong current password or weak/denylisted password → error.', 'a' => 'Current password required.'],
            'portal.security.sessions.end' => ['p' => 'Sign out other devices.', 'd' => 'sessions, security_events', 's' => 'All other sessions of the account deleted; event logged.', 'f' => 'Anonymous → redirect to sign-in.', 'a' => 'Current session remains; other sessions end immediately.'],

            // Administration — general
            'admin.dashboard' => ['p' => 'Officer dashboard with role-appropriate queues.', 'd' => 'counts from members, applications, payments, enquiries, system_alerts', 'a' => 'Shows only figures the officer’s permissions allow.'],
            'admin.account' => ['p' => 'Own officer account: password and two-factor enrolment.', 'd' => 'users', 'a' => 'Reachable even when enrolment is enforced.'],
            'admin.account.password' => ['p' => 'Change own officer password.', 'd' => 'users, sessions, security_events', 's' => 'Password changed; other sessions ended.', 'f' => 'Wrong current or weak password → error.', 'a' => 'Denylisted demo password refused.'],
            'admin.account.two-factor.start' => ['p' => 'Begin authenticator enrolment.', 'd' => 'session (pending secret)', 's' => 'Secret and otpauth link shown.', 'f' => 'Already enrolled → refused.', 'a' => 'Secret is not stored on the account until confirmed.'],
            'admin.account.two-factor.confirm' => ['p' => 'Confirm enrolment with a code.', 'd' => 'users, audit_events', 's' => 'Encrypted secret saved; 8 recovery codes shown once.', 'f' => 'Wrong code or no pending secret → refused.', 'a' => 'Recovery codes stored hashed; secret encrypted.'],
            'admin.account.two-factor.recovery' => ['p' => 'Regenerate recovery codes.', 'd' => 'users, audit_events', 's' => 'New codes shown once; old codes invalid.', 'f' => 'Not enrolled or wrong current code → refused.', 'a' => 'Needs a current authenticator code; old codes stop working.'],
            'admin.account.two-factor.disable' => ['p' => 'Turn off own two-factor.', 'd' => 'users, audit_events', 's' => 'Two-factor removed.', 'f' => 'Wrong password/code, or enforcement on for a privileged officer → refused.', 'a' => 'Needs both password and a current code.'],
            'admin.audit.index' => ['p' => 'Search the audit trail.', 'd' => 'audit_events', 'a' => 'Append-only; filters by action, officer and date.'],
            'admin.audit.export' => ['p' => 'Export filtered audit events.', 'd' => 'audit_events', 's' => '200 CSV; formula-injection safe.', 'a' => 'Export itself is audited.'],
            'admin.settings.index' => ['p' => 'Organisation settings and launch approvals.', 'd' => 'settings', 'a' => 'Internal settings are not editable.'],
            'admin.settings.update' => ['p' => 'Save typed settings.', 'd' => 'settings, audit_events', 's' => 'Changed values saved and audited.', 'f' => 'Type validation errors; enabling two-factor enforcement while a privileged officer is unenrolled → refused.', 'a' => 'Lockout prevention holds; approval and internal keys cannot be set here.'],
            'admin.settings.approval' => ['p' => 'Record a client launch approval or withdraw it.', 'd' => 'settings, audit_events', 's' => 'Approval recorded with officer and reference.', 'f' => 'Unknown key → 404; missing reference → validation error.', 'a' => 'Launch gates stay closed until approved.'],
            'admin.backups.index' => ['p' => 'Backup register and status.', 'd' => 'backup_runs', 'a' => 'Shows the last successful backup and restore verification.'],
            'admin.backups.store' => ['p' => 'Create an encrypted backup now.', 'd' => 'backup_runs, audit_events; writes the encrypted file', 's' => 'Encrypted file written outside the webroot and registered.', 'f' => 'No public key configured → refused with explanation; failure recorded as a failed run.', 'a' => 'File is sealed to the offline key; the server cannot decrypt it.'],
            'admin.backups.download' => ['p' => 'Download an encrypted backup file.', 'd' => 'backup_runs', 's' => 'Encrypted file streamed.', 'f' => 'Missing file → 404.', 'a' => 'Download audited; content remains encrypted.'],
            'admin.alerts.index' => ['p' => 'Integrity alerts, check history and email channel status.', 'd' => 'system_alerts, integrity_check_runs, alert_notifications', 'a' => 'Shows counts only — no member or amount details.'],
            'admin.alerts.show' => ['p' => 'Single alert with receipts and notification history.', 'd' => 'system_alerts, alert_receipts, alert_notifications', 'a' => 'Delivery statuses are honest (not_configured / accepted_by_mail_server / failed / displayed).'],
            'admin.alerts.check' => ['p' => 'Run the ledger integrity check now.', 'd' => 'integrity_check_runs, system_alerts', 's' => 'Run recorded; alert raised or condition cleared.', 'f' => 'Check error is recorded as an error run and raises an alert.', 'a' => 'A passing run marks open conditions as cleared.'],
            'admin.alerts.acknowledge' => ['p' => 'Acknowledge an alert with a note.', 'd' => 'system_alerts, audit_events', 's' => 'Alert acknowledged with officer and note.', 'f' => 'Note under 10 characters or alert not open → refused.', 'a' => 'Acknowledging does not resolve or hide the banner.'],
            'admin.alerts.resolve' => ['p' => 'Resolve an alert after its condition cleared.', 'd' => 'system_alerts, audit_events', 's' => 'Alert resolved.', 'f' => 'Condition not yet cleared by a passing check → refused.', 'a' => 'Alerts cannot be silently dismissed.'],
            'admin.alerts.email.test' => ['p' => 'Send a verification code to the alert email channel.', 'd' => 'cache (hashed code), alert_notifications', 's' => 'Code accepted by the mail server.', 'f' => 'Email disabled/unconfigured or mail failure → refused and recorded.', 'a' => 'Channel is not trusted until the code is confirmed.'],
            'admin.alerts.email.verify' => ['p' => 'Confirm the alert email channel with the received code.', 'd' => 'settings (alerts.email_verified), audit_events', 's' => 'Channel marked verified.', 'f' => 'Wrong or expired code → refused.', 'a' => 'Only a verified channel is used for alert email.'],

            // Administration — access
            'admin.access.index' => ['p' => 'Officer accounts and recovery queue.', 'd' => 'users, roles, recovery_requests', 'a' => 'Lists roles per officer.'],
            'admin.access.show' => ['p' => 'Single account with roles and history.', 'd' => 'users, role_user, security_events', 'a' => 'No password or secret material shown.'],
            'admin.access.store' => ['p' => 'Create an officer account.', 'd' => 'users, role_user, account_tokens, audit_events', 's' => 'Pending account created; one-time activation link shown once.', 'f' => 'Validation errors; duplicate email refused.', 'a' => 'Account cannot sign in until activated.'],
            'admin.access.roles' => ['p' => 'Change an account’s roles.', 'd' => 'role_user, audit_events', 's' => 'Roles replaced with reason.', 'f' => 'Removing the last system administrator or own access-management → refused.', 'a' => 'Lockout prevention; change audited.'],
            'admin.access.status' => ['p' => 'Suspend, disable or reactivate an account.', 'd' => 'users, sessions, audit_events', 's' => 'Status changed; sessions ended when suspended.', 'f' => 'Acting on self or last administrator → refused.', 'a' => 'Suspension takes effect immediately.'],
            'admin.access.link' => ['p' => 'Issue a password-reset link for an officer.', 'd' => 'account_tokens, outbound_messages, audit_events', 's' => 'Earlier unused links revoked; new link shown once.', 'f' => 'Identity confirmation missing → validation error.', 'a' => 'Only the token hash is stored.'],
            'admin.access.recovery' => ['p' => 'Resolve a recovery request (issue link or close).', 'd' => 'recovery_requests, account_tokens, audit_events', 's' => 'Request closed; link issued if approved.', 'f' => 'Already handled → refused.', 'a' => 'Identity verification is attested by the officer.'],
            'admin.access.two-factor.reset' => ['p' => 'Reset an officer’s two-factor after a lost device.', 'd' => 'users, sessions, audit_events', 's' => 'Two-factor removed; sessions ended.', 'f' => 'Own account or no identity confirmation → refused.', 'a' => 'Never on self; audited with reason.'],

            // Administration — membership
            'admin.applications.index' => ['p' => 'Application review queue.', 'd' => 'membership_applications', 'a' => 'Flags out-of-range birth dates for human review.'],
            'admin.applications.show' => ['p' => 'Single application with timeline.', 'd' => 'membership_applications, application_events', 'a' => 'Private notes and applicant messages are separated.'],
            'admin.applications.decide' => ['p' => 'Approve, decline, request information or add a note.', 'd' => 'membership_applications, application_events, members, membership_numbers, audit_events', 's' => 'Decision recorded; approval creates the member and issues a number.', 'f' => 'Already decided, stale state or duplicate member → refused.', 'a' => 'Approval is exactly-once; numbers are never reused.'],
            'admin.applications.attachments.show' => ['p' => 'Download a supporting document from an application (certificates, referee letters, photographs).', 'd' => 'reads application_attachments, private_files; writes audit_events', 'a' => 'Reviewing officers only; each download audited; served from outside the webroot with nosniff.'],
            'admin.members.index' => ['p' => 'Membership register search.', 'd' => 'members', 'a' => 'Search by name, number, email, phone.'],
            'admin.members.create' => ['p' => 'Manual single-member form.', 'd' => 'none', 'a' => 'Explains the same-name confirmation.'],
            'admin.members.store' => ['p' => 'Add one member manually.', 'd' => 'members, membership_numbers, audit_events', 's' => 'Member created with the next number; no account yet.', 'f' => 'Duplicate email/phone → refused; same name without confirmation and a distinguishing note → refused.', 'a' => 'Same-name exception recorded in admin notes and audit; idempotent.'],
            'admin.members.export' => ['p' => 'Export the register as CSV.', 'd' => 'members', 's' => '200 CSV; formula-injection safe.', 'a' => 'Export audited.'],
            'admin.members.show' => ['p' => 'Member record with history.', 'd' => 'members, users, audit_events', 'a' => 'Finance details only for finance permissions.'],
            'admin.members.update' => ['p' => 'Correct member details with a reason.', 'd' => 'members, audit_events', 's' => 'Changes saved; version incremented.', 'f' => 'Stale version, duplicate email/phone or validation error → refused.', 'a' => 'Membership number immutable; change audited with reason.'],
            'admin.members.status' => ['p' => 'Change membership status.', 'd' => 'members, users, sessions, audit_events', 's' => 'Status changed; account suspended with membership where applicable.', 'f' => 'Invalid transition or missing reason → refused.', 'a' => 'Members are never deleted.'],
            'admin.members.access' => ['p' => 'Issue a portal activation link for a member.', 'd' => 'users, account_tokens, outbound_messages, audit_events', 's' => 'Account created if needed; earlier unused links revoked; link shown once.', 'f' => 'No email, inactive member or no identity confirmation → refused.', 'a' => 'One live link per account.'],
            'admin.imports.members.index' => ['p' => 'Member import batches.', 'd' => 'import_batches', 'a' => 'Shows status and counts.'],
            'admin.imports.members.template' => ['p' => 'Download the member import template.', 'd' => 'none', 's' => '200 CSV.', 'a' => 'Headers match the importer.'],
            'admin.imports.members.preview' => ['p' => 'Upload and validate a member CSV without committing.', 'd' => 'import_batches, import_rows, private_files', 's' => 'Row-level preview with duplicates and same-name matches.', 'f' => 'Wrong type/size or a file already committed → refused.', 'a' => 'Nothing is written to members at preview.'],
            'admin.imports.members.show' => ['p' => 'Preview or result of a member import.', 'd' => 'import_batches, import_rows', 'a' => 'Shows each row’s status.'],
            'admin.imports.members.commit' => ['p' => 'Commit a previewed member import.', 'd' => 'members, membership_numbers, import_rows, audit_events', 's' => 'Valid rows created exactly once.', 'f' => 'Batch not previewed, approval gate closed, or same-name rows unconfirmed → refused.', 'a' => 'Idempotent; same-name rows require explicit confirmation.'],
            'admin.imports.members.cancel' => ['p' => 'Cancel a previewed member import.', 'd' => 'import_batches, audit_events', 's' => 'Batch cancelled.', 'f' => 'Not a member batch → 404; already committed/cancelled → refused.', 'a' => 'Cannot cancel a financial batch.'],
            'admin.messages.index' => ['p' => 'Outbound message queue.', 'd' => 'outbound_messages', 'a' => 'Never shows raw tokens.'],
            'admin.messages.show' => ['p' => 'Single prepared message.', 'd' => 'outbound_messages', 'a' => 'Delivery state is honest.'],
            'admin.messages.delivered' => ['p' => 'Attest manual delivery of a message.', 'd' => 'outbound_messages, audit_events', 's' => 'Marked delivered with officer and note.', 'f' => 'Not pending or no confirmation → refused.', 'a' => 'Only a human attestation marks manual delivery.'],
            'admin.messages.cancel' => ['p' => 'Cancel a pending message.', 'd' => 'outbound_messages, audit_events', 's' => 'Cancelled with note.', 'f' => 'Not pending or short note → refused.', 'a' => 'Cancelled messages cannot later be marked delivered.'],
            'admin.enquiries.index' => ['p' => 'Enquiry queue.', 'd' => 'enquiries', 'a' => 'Filter by status.'],
            'admin.enquiries.show' => ['p' => 'Single enquiry with notes.', 'd' => 'enquiries, enquiry_notes', 'a' => 'Shows assignee and history.'],
            'admin.enquiries.update' => ['p' => 'Change status, assignee or add a note.', 'd' => 'enquiries, enquiry_notes, audit_events', 's' => 'Saved; resolved_at set when resolved.', 'f' => 'Invalid status or assignee who is not an officer → refused.', 'a' => 'Assignee must be an active officer.'],

            // Administration — content and events
            'admin.content.index' => ['p' => 'Content overview.', 'd' => 'pages, posts, galleries', 'a' => 'Shows draft and published counts.'],
            'admin.content.media' => ['p' => 'Media library.', 'd' => 'media', 'a' => 'Lists re-encoded images.'],
            'admin.content.media.upload' => ['p' => 'Upload an image.', 'd' => 'media; writes public/media', 's' => 'Image re-encoded (metadata stripped) and stored.', 'f' => 'Not an image, too large or rights not confirmed → refused.', 'a' => 'EXIF/GPS removed by re-encoding.'],
            'admin.content.pages.edit' => ['p' => 'Edit a managed page.', 'd' => 'pages, page_revisions', 'a' => 'Shows revisions and the published one.'],
            'admin.content.pages.save' => ['p' => 'Save a new page revision (draft).', 'd' => 'page_revisions, audit_events', 's' => 'New immutable revision.', 'f' => 'Validation errors.', 'a' => 'Saving never publishes.'],
            'admin.content.pages.publish' => ['p' => 'Publish a revision of this page.', 'd' => 'pages, audit_events', 's' => 'Revision becomes public.', 'f' => 'Revision of another page → 404; approval gate closed → refused.', 'a' => 'Only the page’s own revisions can be published.'],
            'admin.content.pages.unpublish' => ['p' => 'Withdraw a page.', 'd' => 'pages, audit_events', 's' => 'Page returns to placeholder.', 'f' => 'Already unpublished → no change.', 'a' => 'Audited.'],
            'admin.content.list' => ['p' => 'List news, notices, activities, leaders, galleries or documents.', 'd' => 'posts, activities, leaders, galleries, documents', 'f' => 'Unknown type → 404.', 'a' => 'Type-scoped.'],
            'admin.content.create' => ['p' => 'New content form for a type.', 'd' => 'none', 'f' => 'Unknown type → 404.', 'a' => 'Type-specific fields.'],
            'admin.content.store' => ['p' => 'Create a content item (draft).', 'd' => 'posts / activities / leaders / galleries / documents, audit_events', 's' => 'Draft created.', 'f' => 'Validation errors; unknown type → 404.', 'a' => 'New content is never published automatically.'],
            'admin.content.edit' => ['p' => 'Edit a content item.', 'd' => 'content tables', 'f' => 'Item of another type → 404.', 'a' => 'Type-scoped binding.'],
            'admin.content.update' => ['p' => 'Update a content item.', 'd' => 'content tables, audit_events', 's' => 'Saved and audited.', 'f' => 'Validation errors; id of another type → 404.', 'a' => 'Cannot edit across types.'],
            'admin.content.status' => ['p' => 'Publish, archive or return to draft.', 'd' => 'content tables, audit_events', 's' => 'Status changed.', 'f' => 'Invalid transition or approval gate closed → refused.', 'a' => 'Publication audited.'],
            'admin.content.galleries.items' => ['p' => 'Add an image to a gallery.', 'd' => 'gallery_items, media, audit_events', 's' => 'Image re-encoded and attached.', 'f' => 'Rights not confirmed, no alt text or invalid image → refused.', 'a' => 'Every gallery image has alt text and rights confirmation.'],
            'admin.events.index' => ['p' => 'Events list.', 'd' => 'events', 'a' => 'Shows status and audience.'],
            'admin.events.create' => ['p' => 'New event form.', 'd' => 'none', 'a' => 'Times entered in Lagos time.'],
            'admin.events.store' => ['p' => 'Create an event (draft).', 'd' => 'events, audit_events', 's' => 'Draft event created.', 'f' => 'Validation errors; end before start → refused.', 'a' => 'Never published on creation.'],
            'admin.events.edit' => ['p' => 'Edit an event.', 'd' => 'events', 'a' => 'Shows RSVP counts.'],
            'admin.events.update' => ['p' => 'Update an event.', 'd' => 'events, audit_events', 's' => 'Saved and audited.', 'f' => 'Validation errors.', 'a' => 'Audience change takes effect immediately.'],
            'admin.events.status' => ['p' => 'Publish, cancel or complete an event.', 'd' => 'events, audit_events', 's' => 'Status changed.', 'f' => 'Invalid transition (e.g. draft → cancelled) → refused.', 'a' => 'Transitions follow the documented state machine.'],
            'admin.events.responses' => ['p' => 'Download RSVP responses.', 'd' => 'event_rsvps, members', 's' => '200 CSV; formula-injection safe.', 'a' => 'Export audited.'],

            // Administration — finance
            'admin.finance.index' => ['p' => 'Finance overview and reconciliation summary.', 'd' => 'LedgerQuery', 'a' => 'Totals derived, never stored.'],
            'admin.finance.categories.index' => ['p' => 'Fee categories.', 'd' => 'fee_categories', 'a' => 'Shows active state.'],
            'admin.finance.categories.store' => ['p' => 'Create a fee category.', 'd' => 'fee_categories, audit_events', 's' => 'Category created.', 'f' => 'Duplicate code or validation error → refused.', 'a' => 'Code unique, upper-cased.'],
            'admin.finance.categories.update' => ['p' => 'Update a fee category.', 'd' => 'fee_categories, audit_events', 's' => 'Saved; version incremented.', 'f' => 'Stale version → refused; validation errors.', 'a' => 'Optimistic concurrency prevents lost updates.'],
            'admin.finance.assessments.index' => ['p' => 'Assessments list.', 'd' => 'assessments', 'a' => 'Draft/posted/discarded shown.'],
            'admin.finance.assessments.create' => ['p' => 'New assessment form.', 'd' => 'fee_categories, members', 'a' => 'Targets all active members or a selection.'],
            'admin.finance.assessments.store' => ['p' => 'Create a draft assessment.', 'd' => 'assessments, audit_events', 's' => 'Draft created.', 'f' => 'Validation or amount errors → refused.', 'a' => 'Draft has no ledger effect.'],
            'admin.finance.assessments.show' => ['p' => 'Assessment with targeted members.', 'd' => 'assessments, member_obligations', 'a' => 'Shows obligations once posted.'],
            'admin.finance.assessments.post' => ['p' => 'Post a draft assessment, creating obligations.', 'd' => 'assessments, member_obligations, audit_events', 's' => 'One obligation per targeted member; assessment immutable.', 'f' => 'Not draft, no targets or duplicate submission → refused/replayed.', 'a' => 'Exactly-once posting.'],
            'admin.finance.assessments.discard' => ['p' => 'Discard a draft assessment.', 'd' => 'assessments, audit_events', 's' => 'Discarded.', 'f' => 'Not draft → refused.', 'a' => 'Posted assessments cannot be discarded.'],
            'admin.finance.obligations.adjust' => ['p' => 'Waive, correct, increase or cancel an obligation.', 'd' => 'obligation_adjustments, audit_events', 's' => 'Signed adjustment appended.', 'f' => 'Invalid kind, amount, date, reason, or would make the balance negative → refused.', 'a' => 'Original obligation untouched; idempotent.'],
            'admin.finance.payments.index' => ['p' => 'Payments list with filters.', 'd' => 'payments', 'a' => 'Pending queue visible to verifiers.'],
            'admin.finance.payments.create' => ['p' => 'Record payment form.', 'd' => 'members', 'a' => 'Amount entered in naira, stored as kobo.'],
            'admin.finance.payments.store' => ['p' => 'Record a received payment (pending).', 'd' => 'payments, private_files, audit_events', 's' => 'Pending payment with optional evidence.', 'f' => 'Duplicate bank reference, invalid amount or file → refused and uploaded evidence removed.', 'a' => 'No ledger effect until verified; idempotent.'],
            'admin.finance.payments.show' => ['p' => 'Payment with allocations and reversals.', 'd' => 'payments, payment_allocations, financial_reversals', 'a' => 'Shows who recorded and verified.'],
            'admin.finance.payments.evidence' => ['p' => 'View payment evidence.', 'd' => 'private_files', 's' => 'File streamed from private storage.', 'f' => 'No evidence → 404.', 'a' => 'Never publicly addressable.'],
            'admin.finance.payments.verify' => ['p' => 'Verify a pending payment.', 'd' => 'payments, audit_events', 's' => 'Payment verified; counts toward the ledger.', 'f' => 'Recorder verifying own payment, not pending → refused.', 'a' => 'Segregation of duties.'],
            'admin.finance.payments.reject' => ['p' => 'Reject a pending payment with a reason.', 'd' => 'payments, audit_events', 's' => 'Rejected; evidence kept as the record.', 'f' => 'Not pending or short reason → refused.', 'a' => 'Rejected payment never affects balances.'],
            'admin.finance.payments.allocate' => ['p' => 'Allocate verified funds to obligations.', 'd' => 'payment_allocations, audit_events', 's' => 'Allocations created.', 'f' => 'Over-allocation, other member’s obligation, unverified payment → refused.', 'a' => 'Composite keys forbid cross-member allocation; idempotent.'],
            'admin.finance.payments.reverse' => ['p' => 'Reverse a verified payment.', 'd' => 'financial_reversals, payments, payment_allocations, audit_events', 's' => 'Dependent allocations reversed, then the payment.', 'f' => 'Not verified, no confirmation, or already reversed → refused.', 'a' => 'Ledger exceptions stay empty; idempotent.'],
            'admin.finance.allocations.reverse' => ['p' => 'Reverse a single allocation.', 'd' => 'financial_reversals, payment_allocations, audit_events', 's' => 'Allocation reversed; funds return to unallocated credit.', 'f' => 'Already reversed or missing reason → refused.', 'a' => 'Append-only reversal record.'],
            'admin.finance.members.show' => ['p' => 'A member’s ledger and statement.', 'd' => 'LedgerQuery', 'a' => 'Printable; boundaries in Lagos time.'],
            'admin.finance.members.statement.csv' => ['p' => 'Download a member statement as CSV.', 'd' => 'LedgerQuery', 's' => '200 CSV.', 'a' => 'Totals match the on-screen statement.'],
            'admin.finance.reports.index' => ['p' => 'Financial reports.', 'd' => 'LedgerQuery', 'a' => 'Period boundaries in Lagos time.'],
            'admin.finance.reports.export' => ['p' => 'Download a report as CSV.', 'd' => 'LedgerQuery', 's' => '200 CSV.', 'f' => 'Unknown report → 404.', 'a' => 'Export audited.'],
            'admin.finance.imports.index' => ['p' => 'Historical financial import batches.', 'd' => 'import_batches', 'a' => 'Shows status and totals.'],
            'admin.finance.imports.template' => ['p' => 'Download a financial import template.', 'd' => 'none', 's' => '200 CSV.', 'f' => 'Unknown kind → 404.', 'a' => 'Headers match the importer.'],
            'admin.finance.imports.preview' => ['p' => 'Upload and validate historical transactions.', 'd' => 'import_batches, import_rows, private_files', 's' => 'Row-level preview with totals.', 'f' => 'Invalid file or already committed → refused.', 'a' => 'Nothing posted at preview.'],
            'admin.finance.imports.show' => ['p' => 'Financial import preview or result.', 'd' => 'import_batches, import_rows', 'a' => 'Totals reconcile with the file.'],
            'admin.finance.imports.commit' => ['p' => 'Commit a previewed financial import.', 'd' => 'assessments, member_obligations, payments, payment_allocations, import_rows, audit_events', 's' => 'Records posted exactly once.', 'f' => 'Not previewed, approval gate closed, errors present → refused.', 'a' => 'Idempotent; file committed at most once.'],
            'admin.finance.imports.cancel' => ['p' => 'Cancel a previewed financial import.', 'd' => 'import_batches, audit_events', 's' => 'Cancelled.', 'f' => 'Member batch → 404; already committed → refused.', 'a' => 'Kind-scoped.'],
        ];
    }
}
