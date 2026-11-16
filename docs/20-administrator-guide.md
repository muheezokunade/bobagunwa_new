# Administrator and membership guide

This guide is for the System Administrator and for the Membership and Secretariat officers. It explains, step by step, how to sign in, deal with the work waiting on the **Overview** page, review membership applications, deliver private account links, keep the member register, import existing members, manage officer accounts, handle enquiries, read the audit history, record launch approvals and take backups. Button names, page names and field labels are written exactly as they appear on screen.

> All names, numbers and amounts in this guide are synthetic examples. Official amounts, role assignments and policies still await the association's approval (see `docs/23-client-decisions.md`).

## Who can do this

Permissions come only from the roles assigned to your account. If a menu item described here is missing, your role does not include it.

| Task | Role(s) that can do it |
|---|---|
| Review, approve or reject applications | Membership Officer |
| Open **Messages to deliver** and record delivery | Membership Officer, System Administrator |
| View the **Member register** | Membership Officer, Secretariat Officer, Treasurer, Payment Verifier, Executive (read-only oversight) |
| Correct a member record; suspend or reactivate | Membership Officer |
| Issue member activation or password reset links | Membership Officer |
| **Export register (CSV)** | Secretariat Officer |
| **Import members** | Secretariat Officer |
| **Enquiries** | Secretariat Officer |
| **Officers and access**, account recovery queue | System Administrator |
| **Audit history** | System Administrator, Executive (read-only oversight) |
| **Settings** and **Launch approvals** | System Administrator |
| **Backups** | System Administrator |

The System Administrator role is technical only. It has no membership-approval powers and no treasury powers: it cannot approve applications, record or verify payments, or see finance pages. This keeps technical control separate from membership and money decisions.

## Signing in

1. Open the website and choose **Member sign in** (or go to `/login`).
2. Enter your **Email address** and **Password**, then press **Sign in**.
3. Officers are taken to the **Overview** page of the administration area. If your account is also linked to a member record, **My membership** in the top bar opens your own portal.
4. To leave, press **Sign out** in the top bar.

Notes:

- Five wrong attempts in a minute cause a short lock-out. Wait a minute and try again.
- "This account is not active" means your account has been suspended or disabled. Ask another administrator.
- To change your own password, open **My account → Password and sessions** in the administration menu. If you have forgotten it, use **Forgotten your password?** on the sign-in page and press **Request help**; the request joins the recovery queue for another administrator (see "Account recovery queue").
- Passwords must be 12 to 128 characters, must not contain your email address, membership number or the words "bobagunwa" or "password", and must use at least five different characters.

## The Overview page

The **Overview** page shows tiles for the work queues your role can see. Press a tile to open that list.

| Tile | Opens |
|---|---|
| Applications awaiting review | Applications with status Received or Under review |
| Flagged for eligibility review | Open applications whose eligibility screening raised a flag |
| Messages awaiting delivery | Prepared messages that are Pending or Failed |
| Active members | The register filtered to Active |
| New enquiries | Enquiries with status New |
| Account recovery requests | The recovery queue on **Officers and access** |

Officers who can read the audit history also see **Recent activity** with a link to **Full audit history**.

## Reviewing a membership application

Open **Applications**. The list shows **Open (awaiting a decision)** by default. You can filter by **Status**, **Eligibility** (**Eligible** or **Flagged for review**) and **Search** (reference, name or email), then press **Filter**. Press **Review** to open an application.

### Reading the warnings

- **Eligibility screening flagged this application.** The date of birth is outside the confirmed range (1 January 1980 to 31 December 1982), or the email or phone matches an existing member. A flag is not a decision and does not block approval. It means a person must consider the application. Check the details with the applicant before deciding.
- **Possible existing member(s) with the same name, email or phone.** Resolve this before approving. The system refuses to approve while a member with the same email address or phone number exists. A match on name alone does not block approval, so check it yourself.
- **Other applications with the same email or phone.** Earlier or parallel applications from the same contact details. Open them to check.

### Recording each step

Every step uses the **Record a decision** panel. The **Next step** list offers only the steps allowed from the current status: approval, rejection and requests for information are offered only once the application is Under review.

1. **Start the review.** For a Received application, choose **Under review**. Write the **Review note (officers only)** (always required) and press **Record decision**.
2. **Request more information.** Choose **More information requested**. Write the review note and a **Message to the applicant** (required for this step). Press **Record decision**. The applicant can reply from the tracking page; their reply moves the application back to Under review automatically. You can also choose **Under review** yourself to continue without a reply.
3. **Approve.** Choose **Approved**. Write the review note. The message to the applicant is optional; if left blank a standard welcome is used. Press **Record decision** and confirm. Approval is final. It creates the member record with a permanent membership number, creates a pending portal account and shows the one-time activation link (see the next section).
4. **Reject.** Choose **Not approved**. Write the review note and a **Message to the applicant** (required). Press **Record decision** and confirm. Rejection is final.

### What the applicant sees and what stays private

- The applicant follows progress on **Track an application** using their reference and private tracking code. They see the status and the text you wrote in **Message to the applicant**.
- The **Review note (officers only)** is never shown to the applicant. In **Review history** it is marked **Officers only**; applicant-facing text is marked **Shown to applicant**.
- A message is also prepared under **Messages to deliver** when you request information, reject or approve.

## Delivering one-time links and recording delivery

Activation and password reset links are private. The system stores only a scrambled copy, so **the link is shown once**, on the page you see straight after issuing it. It expires after 7 days (activation) or 60 minutes (password reset) and works once.

1. When the box headed "Activation link for …" (or "Password reset link for …") appears, press **Copy link**, or select the link text and copy it.
2. Open **Messages to deliver** and the matching message (or press **Record delivery** in the box, where shown). The stored message text says "[One-time link withheld from records]": the link is not kept, so paste the link you copied into the message you send.
3. Send the message and link to the verified person through the association's approved private channel.
4. Only after you have actually sent it, return to the message and complete **Record delivery**: fill in **How was it sent?** (for example "Sent by WhatsApp to the verified number on 16 Nov"), tick **I have sent this message to the recipient.**, then press **Record delivery**.
5. If a message should not be sent, fill in **Reason for cancelling** and press **Cancel message**.

Never record delivery before sending. If the link was lost before it was sent, issue a new one from the member record or officer account; issuing a new link stops any earlier unused link from working. After an approval, the **Record delivery** button opens the activation-link message. Two messages are prepared: the decision message and the activation link message.

If **Also send account links by email through the configured mail server** is switched on in Settings, the system also emails the link. Such messages show **Sent by email**, which means the mail server accepted them, not that the person received them.

## The member register

Open **Member register**. Records are never deleted.

- **Search** by number, name, email or phone. Filter by **Status** (**Active**, **Suspended**, **Inactive**), **Origin** (**Approved application**, **Imported**, **Entered manually**), **Portal account** (**No account**, **Awaiting activation**, **Active**) or tick **Missing contact or date details**. Press **Filter**, then **Open** on a row.
- Warnings at the top show missing details and **Other records have the same name**. Check whether these are the same person before issuing access.

### Correcting a record

1. In **Correct the record**, change only the fields you have verified. The membership number cannot be changed.
2. Fill in **Reason for the correction** (for example "Email confirmed by the member in person on 16 Nov").
3. Press **Save correction**. Each changed field and your reason appear in **Change history**.

If someone else changed the record while you were editing, reload the page and try again. Changing the email also changes the member's sign-in email.

### Suspending or reactivating

1. In **Membership status**, choose the **New status** and give a **Reason**.
2. Press **Change status** and confirm. A suspended or inactive member cannot sign in, and their open sessions end at once. Choose **Active** to reactivate.

### Issuing an activation or password reset link

1. Confirm the member's identity and contact details through a channel the association already holds for them.
2. In **Portal account**, tick **I have verified this member's identity and contact details.**
3. Press **Issue activation link** (no account yet, or not yet activated) or **Issue password reset link** (active account).
4. Deliver the link and record delivery as described above.

The member must have an email address on record, and must be Active.

## Importing existing members

Open **Import members**.

1. Choose **Download the CSV template** and fill in one row per member. Only **full_name** is required. Optional columns: email, phone, date_of_birth, membership_number, joined_on, ijebu_town, city, occupation, address. Write dates as YYYY-MM-DD or DD/MM/YYYY. Leave membership_number empty to issue the next number. At most 2,000 rows; save as CSV (UTF-8).
2. Under **Upload a file for preview**, choose the **CSV file** and press **Preview import**. Nothing is saved yet.
3. Read the counts (**Rows**, **Valid**, **Invalid**, **Duplicates**) and the **Messages** column for each row. Duplicates include the same email, phone, number or name repeated in the file or already in the register.
4. If any row is invalid or duplicate, the file cannot be imported. Press **Cancel preview**, correct those rows in the original file and upload it again. There is no override for a row flagged as a duplicate.
5. When every row passes, tick **I confirm this list is the association's approved source and has been checked.** and press **Complete import**.

Completing an import never creates login accounts; issue activation links later, member by member, after verifying identity. The import is safe to repeat: pressing **Complete import** again changes nothing, and the same file cannot be imported twice. If the register changed after the preview, you will be asked to upload the file again.

## Officer accounts and roles

Open **Officers and access** (System Administrator).

### Creating an officer

1. In **Add an officer**, enter **Full name** and **Email**.
2. Tick only the **Roles** the association has approved for this person.
3. To link the account to their member record, enter the **Membership number (optional)**.
4. Fill in **Reason and approval reference** (for example "Elected treasurer, AGM minutes 12 Oct 2026").
5. Press **Grant officer access** and confirm. If the person had no account, a one-time activation link is shown. Deliver it and record delivery.

The **Role catalogue** panel lists each role and its permissions. Payment recording and payment verification belong to different roles.

### Changing roles or status

Press **Manage** on an officer. Tick the officer roles, give a **Reason and approval reference** and press **Save roles**. The officer is signed out so the change applies at once. Use **Account status** (**Active**, **Suspended (temporary)**, **Disabled**) with a **Reason**, then **Change status**. Use the **Activation link** or **Password reset link** panel, ticking **I have verified this person's identity.**, to issue a new link.

Safeguards:

- You cannot change your own roles or status. Ask another administrator.
- The last active account able to manage access cannot lose that role or be suspended or disabled. Grant the role to another officer first.
- An account awaiting activation becomes active only when the person uses their link.

### Account recovery queue

Requests made through **Forgotten your password?** appear under **Account recovery requests**.

1. Verify the person's identity through a channel the association already holds. Never rely on details supplied only in the request.
2. Choose **Decision**: **Issue a one-time link** or **Dismiss**.
3. Fill in **Note** (how identity was checked, or why it is dismissed). To issue, tick **I have verified this person's identity (required to issue a link).**
4. Press **Save decision**. Deliver any link shown and record delivery.

## Enquiries

Open **Enquiries** (Secretariat Officer). Use the buttons to show **Open (new and in progress)**, **New**, **In progress**, **Resolved**, **Closed as spam** or **Assigned to me**. Press **Open** on an enquiry.

1. Reply to the person outside the system (for example by email from the secretariat mailbox). The platform does not send replies.
2. In **Update**, set **Status** and **Assigned to**, and add an **Internal note (optional)** recording what was done.
3. Press **Save**.

## Audit history

Open **Audit history**. Choose **Log**: **Officer actions** or **Security events**. Filter by **Action starts with** (or **Event starts with**), **Subject type** (for example Member, Payment), **From** and **To**, then press **Filter**. Press **Show details** to see the recorded context. Entries cannot be edited or deleted. Times are Lagos time; **Export audit log (CSV)** gives the officer-action log in UTC.

## Settings and launch approvals

Open **Settings** (System Administrator). Change fields under **Organisation settings** and press **Save settings**. Settings never hold passwords or keys. The next membership number to be issued is shown under **Membership number digits**.

**Launch approvals** record that the association formally approved a step. To record one, enter the **Approval reference** (for example "Executive meeting minutes, 12 Oct 2026, item 4") and press **Record approval**. **Withdraw approval** reverses it. In production, each gate blocks the following until approved:

| Approval | Blocks in production |
|---|---|
| Membership-number format approved by the association | Issuing any membership number: approving applications and completing member imports |
| Verified existing-member list approved for import | **Complete import** on **Import members** |
| Historical financial records approved for import | Completing a historical financial records import |

Outside production the page says so, and these actions are allowed for testing.

## Backups

Open **Backups** (System Administrator). The server holds only the off-site custodian's public key, so it can create backups but cannot read them.

1. Press **Create encrypted backup** and confirm. (The button is disabled until the custodian's public key is configured on the server; ask the technical maintainer.)
2. In **Backup and restore history**, press **Download** on the new backup.
3. Note the SHA-256 fingerprint (hover over the short value to see it in full) and pass it to the custodian with the file.
4. Transfer the file to the approved off-site custodian.

The server also makes a backup automatically each night (02:00 UTC, 03:00 Lagos time) when the key is configured, and keeps only the 14 most recent backup files. Download and hand over the ones the association needs before they are removed.

Restoring is not done from this page. It is a command-line drill carried out by the technical maintainer, with the custodian's key, into a separate empty database (`php artisan boba:backup:restore`), never into the live database. Its result appears here as a **Restore verification** with "All checks passed." or a list of problems.

## Initial administrator provisioning

There is no default password. The first administrator is created by the technical maintainer on the server:

```
php artisan boba:admin:create admin@example.org "Adebayo Example"
```

1. The command prints a one-time activation link and its expiry (in UTC, 7 days ahead). By default it grants the System Administrator role; `--role=` accepts other role keys as a comma list.
2. The maintainer sends the link privately to the named person.
3. The person opens the link, chooses a **New password**, repeats it in **Confirm new password** and presses **Activate account**.
4. That administrator then creates the other officers in **Officers and access**.

## If something goes wrong

- **A link was lost or expired:** issue a new one. The old one stops working.
- **"…is disabled until the association records its approval in Settings → Launch approvals":** the step needs a launch approval. Do not record an approval the association has not given.
- **Approval refused because of a duplicate:** check the existing member record before doing anything else.
- **An import keeps failing:** read the row messages, correct the source file and upload again. Never edit data to force it through.
- **You recorded delivery by mistake:** it cannot be undone. Issue a new link (which cancels the old one) and add a note in the member's record explaining what happened.
- **Anything unexpected in Audit history or Backups:** stop and contact the technical maintainer.
