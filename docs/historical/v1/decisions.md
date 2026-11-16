# Decision register

| ID | Decision | Status | Safe current position |
| --- | --- | --- | --- |
| D-001 | Target hosting | Provisional | Same-origin Cloudflare Worker + D1; Netlify remains production during build |
| D-002 | Authentication library/hosting | Workers Free rejected; production choice pending | Better Auth 1.7.7 passes the lifecycle on an isolated Cloudflare Worker, but password operations consume 89–174ms CPU against the Free 10ms limit. Do not weaken scrypt. Decide between explicitly approved Workers Paid, a separate secure hash runtime, a vetted managed auth service, or a separately approved passwordless redesign. |
| D-003 | Public registration | Confirmed application policy | Public membership applications require no login, invitation or referral. Public account registration remains disabled; accounts activate only after approval. |
| D-004 | Membership-number format | Provisional implementation; display approval pending | Local workflow allocates immutable, never-reused `BOB-######` numbers. Confirm the visible production format before creating real members. |
| D-005 | Membership approver(s) | Permission confirmed; named assignments pending | Only `members.approve` can review/request information/approve/reject. No real production assignment until the association supplies authorised names. |
| D-006 | Fee types/amounts | Pending approval | Configurable; no official amounts seeded |
| D-007 | Payment posting/verifying authority | Pending approval | Separate permissions; least privilege |
| D-008 | Payment reversal authority | Pending approval | Separate permission and mandatory reason/audit event |
| D-009 | Allocation priority | Pending approval | No automatic allocation policy enabled |
| D-010 | Overpayment/refund policy | Pending approval | Preserve as unallocated member credit; no automatic refund |
| D-011 | Executive report access | Pending approval | Approved aggregate summaries only by default |
| D-012 | Public member directory | Pending approval | No private member data published |
| D-013 | Account-link delivery | Implemented local fallback; production procedure pending approval | No paid email provider. An authorised administrator verifies identity and receives a one-time activation/recovery link for delivery through an approved private channel; the website never claims an email was sent |
| D-014 | Official contact/history/content | Pending verification | Keep checklist; do not select conflicting facts arbitrarily |
| D-015 | Production cutover | Requires explicit approval | No production replacement or real-data migration |
| D-016 | Paid services/plan | Requires explicit approval | No unapproved subscription or automatic upgrade |
| D-017 | Sites staging finance migrations | Isolated auth benchmark only | The Sites migration service rejects multi-statement SQLite triggers. Canonical Wrangler migrations retain the finance invariant triggers; the owner-only Sites benchmark package omits them and must never hold live financial data. |
