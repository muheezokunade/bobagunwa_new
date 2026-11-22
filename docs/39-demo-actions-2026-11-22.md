# Demo actions — 22 November 2026

From the full Gemini notes and transcript of the "Bobagunwa Demo" (Gunwa Abiodun Orehin and the developer, 66 minutes). Status as of 22 Nov 2026; update this table as items close.

## Done

| Item | Where |
|---|---|
| Leadership: menu tab stays "Leadership"; page headed "Giwa-in-Council" (all of the Giwa, chiefs and executives) | `66fca1d` |
| Large photos for Giwa, Otun Giwa, Eta Giwa; compact cards for chiefs and executives, separate sections | `66fca1d` |
| Profile pop-ups when a leader is clicked (no separate pages) | `6885a41` |
| Dates entered as day / month / year with three-letter months ("15 Jun 1981"), shown the same everywhere | `66fca1d` |
| Applications outside 1980–1982 are flagged for an officer, never rejected automatically | already built (D-027 recorded) |
| Official content loaded: About ("Who we are"), Giwa's welcome address on the home page, 37 leadership entries | `6885a41`, `docs/38` |
| History on the Awujale kept as an unpublished draft (the Awujale has passed; text says "living") | `6885a41` |
| Application reference + private tracking code; role-based access; finance CSV reports; levy categories can be created by the treasurer | already built |
| Partial payments: a part-payment leaves the remainder outstanding on the statement | already built; display to be made clearer with the receipt upload |
| **Application form = the association's Member Profile form**, with country of residence and the eight supporting documents (private, reviewer-only, audited downloads; kept by the nightly clean-up; copied profile fields to the member on approval); association's privacy notice wording | this commit — see the note below on PHP upload limits |

| **Two-step membership approval**: new Membership Initiator role (the General Secretary) reviews and recommends; the Membership Officer (screening-committee chair) approves or rejects, never on his own recommendation; asking for more information clears the recommendation; committee reviews in the app only | this commit |

## In progress / to build

| # | Item | Notes |
|---|---|---|
| 3 | **Members upload payment receipts** | Pick the levy, amount pre-filled (part-payment allowed), upload receipt, payer name if another account paid; payment verifier confirms against the bank alert. |
| 4 | **Report of who has paid a particular levy** (paid / part-paid / unpaid), CSV export; levy categories CSV | Requested in the demo. |
| 5 | **MFA for administrators only** (not members) | Built and switched off; scope to admin accounts and enable after enrolment (D-024). |
| 6 | **Admin access recovery** | System Administrator (Abiodun's committee) resets other admins' access with one-time links; the Giwa's account can reset even the System Administrator; one top-level administrator. Executive read-only access = Giwa and General Secretary. |
| 7 | **Five values** as short English + Yoruba tiles from the five "We are committed to" points (fifth = Service) | Draft by the developer; Yoruba to be confirmed by the association. |
| 8 | **History & heritage**: past Awujales with picture, years and summary, full text in a pop-up | Structure now; content from Abiodun (redraft in the past tense; Ijebu museum sources). |

## Waiting for the association (Gunwa Abiodun Orehin)

| Item | Status |
|---|---|
| Redrafted history; royal lineage / past Awujales information | awaited |
| OneDrive photo link (2023, 2025, 2026 folders; Contact-page photo for About) | awaited |
| Past activities documentation (for activity pages) | awaited |
| Spreadsheet of existing members who updated their details (import, D-019) | **deferred** — import left for later (22 Nov) |
| Membership form and required attachments | **received** (Member Profile form + attachment list, 22 Nov) |
| Giwa's photograph for the welcome address; leaders' photographs and short biographies | awaited |

**PHP upload limits (before testing applications on staging or production):** applications now send up to ten documents of up to 5 MB each. Set, for the domain, `post_max_size = 64M`, `upload_max_filesize = 12M`, `max_file_uploads = 20` (Namecheap: cPanel → Select PHP Version → Options, or the `user.ini` kept with the staging config). `boba:preflight` warns until this is done; an oversized submission returns the applicant to the form with a clear message.

**Privacy:** the form collects birth certificates, school certificates, referee letters and photographs. They are stored outside the webroot, visible only to officers who review applications, and every download is audited. How long they are kept after a decision is part of the legal review (D-016).

## Decisions needed

| Question | Options |
|---|---|
| Imported members' first sign-in | **Decided: one-time link** (D-029) |
| Officers who are also members | **Decided: separate officer login** with a different email (D-028), enforced in the code |
| WhatsApp receipt upload (QR-linked number) | deferred — separate commercial product |
| Online payment (e.g. Paystack) with automatic verification | future option, mentioned in the demo |

## Developer (non-code)

- Share the staging link, the `boba-staging` login and fresh officer links with Abiodun for a few technical testers.
- Production starts from a fresh database: no synthetic accounts or records.
- Handover documentation and architecture overview at completion.
- Scope and commitment-invoice call (Monday or Tuesday evening); invoice after the call.
