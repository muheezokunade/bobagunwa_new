# Client decision register

Single register of decisions the association must make. Each has a **safe current position** that the software enforces until the decision is recorded. "Gate" means the action is technically blocked in production until the approval is recorded under Administration → Settings → Launch approvals.

| ID | Decision | Status | Safe current position in the software |
|---|---|---|---|
| D-001 | Hosting | **Decided** (commercial constraint) | Existing Namecheap Stellar plan; ₦0 new recurring services |
| D-002 | Authentication approach | **Decided** (engineering) | Laravel session authentication; activation-only accounts; no paid auth service |
| D-003 | Public self-registration | **Confirmed policy** | None. Public applications only; accounts after approval |
| D-004 | Visible membership-number format | Pending | Configurable `BOB-` + 6 digits; **gate** `approval.membership_number_format` blocks issuance in production |
| D-005 | Named membership approvers | Pending | Only `applications.review` (Membership Officer role); no real assignments made |
| D-006 | Official dues/levy categories and amounts | Pending | No categories or amounts seeded; treasurer enters approved amounts |
| D-007 | Separation of payment recording and verification; monetary limits | Pending | Different permissions; **two-officer rule on by default**; no limits |
| D-008 | Payment reversal authority | Pending | `finance.reverse` held by Payment Verifier role only; reason mandatory |
| D-009 | Allocation priority | Pending | Manual allocation; "Suggest oldest first" is advisory only |
| D-010 | Overpayment and refunds | Pending | Kept as unallocated credit; no refund workflow |
| D-011 | Report visibility for executives | Pending | Executive role: read-only finance, register and audit |
| D-012 | Public member directory | Pending | None; `/members` redirects to membership information |
| D-013 | Account-link and decision delivery channel; identity-verification procedure | Pending | Officer-assisted delivery with recorded attestation; optional SMTP via existing mailbox (off) |
| D-014 | Backup custodians, off-site location, schedule, retention | Pending | Daily encrypted backups once a public key is set; 14 kept on server; off-site custody not established |
| D-015 | Official public content | Pending | See content table below; nothing unapproved is published by default |
| D-016 | Privacy notice, terms, retention periods, NDPA obligations (legal review) | Pending | Working drafts shown with a "awaiting review" banner |
| D-017 | Deploy the full application to protected staging | **Approved** by Mr Ibrahim (16 Nov 2026) — `v2-staging.bobagunwa.com` only; no production, WordPress, DNS, email or cutover | Deployment tooling verified in a simulation; Namecheap deployment pending SSL (B1) and server access (B2) — `37-staging-deployment-evidence.md` |
| D-018 | Production cutover (domain route, WordPress coexistence, date) | Pending | Not deployed; WordPress untouched |
| D-019 | Verified existing-member roster (86 provisional names) and identity fields | Pending | Not imported; **gate** `approval.member_import` — **Deferred 22 Nov 2026**: existing-member import left for later |
| D-020 | Historical financial records, cut-off date and source documents | Pending | None imported; **gate** `approval.historical_finance` |
| D-021 | Initial production administrator(s) | Pending | Provisioned only by `boba:admin:create`; no default account |
| D-022 | Officer role assignments (treasurer, verifier, secretariat, executive) | Pending | Templates only |
| D-023 | Private GitHub repository for this codebase | **Decided** | `muheezokunade/bobagunwa_new` (`main`); kept separate from the V1 repository `muheezokunade/bobagunwa`, which is not modified |
| D-024 | Require two-factor sign-in for privileged officers (finance writers, access, settings, backups) | Pending | Implemented and tested; **enforcement off**; opt-in enrolment available; lockout-guarded (`32-two-factor-authentication.md`) |
| D-025 | Email channel for integrity alerts (use the existing mailbox?) and who must receive alerts | Pending | Dashboard banner always on for `integrity.alerts` holders; email off until enabled and verified (`31-integrity-alerting.md`) |
| D-026 | Procedure for genuinely distinct members who share a full name | Pending | Officer must confirm and record a distinguishing note (≥ 10 characters); audited (manual add and import) |
| D-027 | Hard eligibility rules for applications (e.g. a minimum age; whether births outside the age-group years are refused or only flagged) | **Decided** at the 22 Nov 2026 demo: never rejected automatically; the officer reviews flagged applications and exceptions are discussed and approved separately | Birth dates outside 1980–1982 are flagged for the officer, not refused |
| D-028 | Officer logins versus member logins | **Decided** 22 Nov 2026 (developer, following the demo): **separate** — an officer who is also a member has a member portal login and a separate officer login with a different email address | Enforced: officer accounts cannot be created on an email already in use and member portal accounts cannot hold officer roles; `boba:preflight` checks it (required in production) |
| D-029 | First sign-in for imported existing members | **Decided** 22 Nov 2026: **single-use activation link** (member sets his own password; no temporary passwords) | As built |

## Content awaiting confirmation (D-015)

| Item | Current handling |
|---|---|
| Official name and Yoruba orthography | Displayed as "Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu" (setting) |
| Values copy | V1 public-site text retained; **"Ìwà Omolúàbí" corrected to "Ìwà Ọmọlúàbí"** — please confirm |
| Motto | Only the crest's "Fun Itesiwaju" is used; V1's decorative line "Àṣà wa, ìṣọ̀kan wa, ọjọ́ ọ̀la wa" was dropped pending confirmation |
| Meaning of "1980–82" | Shown only as on the crest; V1's "EST. 1980–82" claim removed |
| About / history copy | V1 about copy available as an unpublished draft (`boba:content:baseline`); history page empty until approved (client's Awujale narrative contained an outdated living-status description) |
| Leadership names, titles, terms, photographs | None published; 43 provisional client rows not included |
| Secretariat email (`eweso@` vs `secretariat@bobagunwa.com`), phone `+234 816 938 9805`, address | Not shown; enter in Settings and switch on "Publish the contact details" once confirmed |
| Activities, events, news, galleries | Empty until officers publish approved items |
| V1 homepage elements not carried over | numbered "impact" tiles, placeholder news items and "Office of the President" placeholders (fabricated/placeholder content) |
