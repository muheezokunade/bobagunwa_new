# Membership application and eligibility policy

Policy version: `2026-10-11-v1`  
Confirmed: 11 October 2026

## Public application

The membership application is public. Any person may apply without signing in and without an invitation or referral. Submission is only an application for review: it is not membership, does not create a portal account and does not guarantee approval.

The form requires the applicant's name, email address, phone number, date of birth, city, occupation, reason for applying, declaration, privacy consent and a successfully verified Cloudflare Turnstile challenge. The server validates and normalises these values; browser validation is not trusted.

## Eligibility screening

The confirmed date-of-birth criterion is inclusive: **1 January 1980 through 31 December 1982**.

- A valid date inside the range is marked `eligible` for screening purposes only. It still requires an authorised administrator's decision.
- A valid date outside the range is accepted and marked `flagged` with a reason for human review. It is not automatically rejected or admitted.
- A malformed or impossible date is rejected as invalid input before submission.
- Screening version and machine-generated flags are stored with the application so the rule used at submission can be audited later.

No screening result automatically creates membership.

## Duplicate protection

The service checks active and previously approved applications using normalised email and phone values. Database unique indexes close the race between simultaneous active submissions. Approval also checks existing members by email and normalised phone.

Approval allocates one immutable membership number and creates at most one member for an application. The allocation and application-to-member links are unique, and a repeated approval request returns a conflict rather than creating another member.

## Review workflow

The permitted states are:

`submitted` → `under_review` → `changes_requested` or `approved` or `rejected`

An application returned for information may move from `changes_requested` back to `under_review`. Every transition requires a private review note and stores the administrator identity and timestamp. Applicant-facing messages are stored separately from private notes.

Review, request-information, approval and rejection endpoints require the server-side `members.approve` permission. Pending, changes-requested and rejected applicants have no portal identity and cannot enter member routes. Approval creates the member and a seven-day, one-use activation invitation; the applicant chooses a password only through that invitation.

## Status and notifications

The success receipt displays a reference plus a high-entropy verification token. Only the matching pair can retrieve the privacy-minimised status timeline. Tokens are stored only as SHA-256 hashes, expire after 90 days and receive the same generic not-found response when invalid, limiting enumeration.

Important decisions create a notification task for an approved low-cost private channel (`manual_email` initially). The site never claims a message was sent automatically. An authorised administrator copies the prepared message into the approved channel and explicitly records delivery, their identity, timestamp and an operational note. Raw activation tokens are displayed only in the one-time approval receipt and are never saved in notification or audit records.

## Privacy

There is no public application directory or public record endpoint. Full applications, screening flags, private notes, review history and notification history are available only inside the permission-guarded administration area. The applicant status endpoint reveals only the record matched by the reference and secret token. Logs and audit summaries avoid raw application contents and activation secrets.

## Production decisions still required

The provisional membership-number presentation is `BOB-######`. Numbers are immutable and never reused, but the association must approve the visible format before production data is created. The association must also name the people or roles that receive `members.approve` and approve the operational notification channel and identity-verification procedure.
