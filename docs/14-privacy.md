# Privacy requirements

Applies the Nigeria Data Protection Act 2023 (NDPA) and the association's duty of care. **Not legal advice**: the privacy notice and retention periods require review by the association's legal adviser (decision D-016).

## Data inventory

| Data | Source | Purpose | Who can see |
|---|---|---|---|
| Application (name, DOB, Ijebu town, city, occupation, email, phone, reason, declarations) | Applicant | Decide membership | `applications.review` officers; applicant via reference + private code (status and applicant-visible messages only) |
| Member register | Approved application / import / officer | Administration | `members.read` officers; the member (own record) |
| Financial records | Treasury officers | Dues and levies | `finance.read` officers; the member (own records) |
| Enquiries | Visitor | Reply | `enquiries.manage` |
| Security fingerprints | System | Abuse detection | `audit.read` (keyed hash, not raw IP) |
| Payment evidence, documents | Officers | Verification / sharing | Authorised downloads only; files outside webroot |

## Principles implemented

* **Minimisation**: no NIN, biometrics, bank credentials or card data are collected. Sex is not collected; the declaration covers eligibility.
* **No public directory**: the earlier `/members` page now redirects to the membership information page; no member data is public (decision D-012).
* **Purpose separation**: membership officers do not see finances; members see only their own data.
* **Transparency**: privacy notice linked from every form; working draft clearly labelled until approved.
* **Security**: see `13-authentication-and-security.md`; backups encrypted to an off-server key.
* **Correction**: members update contact details in the portal; officers correct verified fields with a recorded reason; history is retained in the audit trail.
* **Applicant privacy**: status look-up needs reference + 90-day private code; failures are generic; private review notes are never shown to applicants.
* **Logs**: secrets and passwords are redacted from audit context; raw tokens never logged.

## Pending decisions before production (see `23-client-decisions.md`)

Data controller contact, retention periods for rejected applications, inactive members, enquiries and logs; deletion/erasure procedure balanced against financial record-keeping; breach-notification procedure; legal approval of notice and terms; whether a DPO or NDPC registration applies to the association.
