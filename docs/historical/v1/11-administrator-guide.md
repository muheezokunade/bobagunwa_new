# Administrator guide

The administrative application has server-side permission guards and persistent D1 workflows for the mandatory membership, finance, access and publishing operations. Use synthetic data until staging, governance and production approval are complete.

## Principles

- Use your own named account; never share credentials.
- Grant only the permissions needed for a role.
- Preview assessments and imports before posting.
- Never edit/delete a posted payment to correct it; use the authorised reversal workflow.
- Verify member identity before sensitive account/profile changes.
- Export personal or financial data only for an approved purpose and store it securely.
- Report suspicious login, unexpected totals, duplicate entries, or missing records immediately.

## Membership application review

1. Sign in with your own named administrative account and open **Applications**. The queue contains all applications, their current status, screening status and submission date.
2. Open an application to inspect its submitted fields, automated screening result, complete review history and notification history. A flagged screening result is an instruction to review, not an automatic rejection.
3. Enter a meaningful private review note and select **Start review**. The system records your identity and timestamp.
4. From `under_review`, choose **Request information**, **Approve** or **Reject**. Request-information and rejection require an applicant-facing message as well as the private note.
5. Approval creates the unique membership record and number once, then displays a one-time seven-day activation link. Copy the prepared private notification into the association-approved delivery channel immediately; the raw link cannot be recovered from the database.
6. After sending the prepared message, enter a delivery note and select **Mark delivered**. Do not mark it delivered before it has actually been sent.

Only accounts with `members.approve` can access these actions. Never copy private review notes into applicant communications. If a likely duplicate is reported, compare the existing member/application securely and do not bypass the conflict.

## Existing-member import and access

1. Download the CSV template from **Members → Import** and preserve its exact headings.
2. Upload an Excel-compatible CSV and review every row error and duplicate. Commit is disabled unless every row is valid.
3. Commit once and retain the job receipt. Repeating the same completed job returns its existing receipt.
4. Open an imported member to issue a one-time activation link. Deliver it only after identity verification through the approved private channel.
5. Suspension blocks the linked account; reactivation restores it. Record a meaningful reason for either action.

## Assessments, payments and reports

1. Create a dues/levy category, then open **New assessment**. Select members, enter decimal naira, preview the target count/total and post once.
2. Record cash or bank-transfer evidence as a pending payment. A duplicate hint requires human review; it does not prove fraud or duplication.
3. Open the payment, allocate no more than the payment or each obligation remainder, and post. Excess remains visible as unallocated credit.
4. Use a waiver/correction for an obligation; never reduce it below active allocations. Use full reversal for an incorrectly posted payment; originals and audit history remain.
5. Print the payment acknowledgement from the detail page. Use **Reports** for reconciled totals and the permissioned CSV export. Compare totals to source bank/cash records under the approved reconciliation schedule.
6. For historical data, follow `historical-finance-import.md`; opening balances are explicitly labelled and are not reconstructed histories.

## Publishing and roles

- Create drafts from Announcements, Events or Content; verify audience/visibility, save revisions, and publish only approved material. Archive to remove a record from public feeds where applicable.
- Page keys `about`, `leadership`, `privacy` and `terms` feed managed public sections. The homepage remains separately curated.
- Grant/revoke roles only from a named user record and enter a reason. The system prevents removing the final active system-administrator assignment, but role reviews still require association oversight.

## Backups and escalation

Follow `10-backup-and-recovery.md`. Never put passphrases or exports in Git, never test a restore against production, and never delete posted evidence as a shortcut. Freeze writes and escalate reconciliation differences, suspected compromise, partial external delivery or unavailable backup custody to the named operational owner.
