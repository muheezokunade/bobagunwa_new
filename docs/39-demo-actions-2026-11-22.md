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

## In progress / to build

| # | Item | Notes |
|---|---|---|
| 1 | **Application form = the association's Member Profile form**, plus attachments | Mandatory: first, middle, surname, date of birth, mobile, email, profession/trade, town of origin. Optional: current address, marital status, profile summary. Keep city of residence; add country of residence (members abroad). Attachments: birth certificate, LG of origin certificate, First School Leaving Certificate, secondary school certificate, other certificates, referee letters with contacts, passport photograph, full-length photograph. Association's own privacy notice wording. |
| 2 | **Two-step membership approval** | General Secretary initiates (reviews, may ask for information, recommends); the membership lead who chairs the screening committee approves. Committee reviews in the app only — no emailed copies of applicants' data. |
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
| Spreadsheet of existing members who updated their details (import, D-019) | **not received by email** — said to be sent by email/WhatsApp |
| Membership form and required attachments | **received** (Member Profile form + attachment list, 22 Nov) |
| Giwa's photograph for the welcome address; leaders' photographs and short biographies | awaited |

## Decisions needed

| Question | Options |
|---|---|
| Imported members' first sign-in | one-time link to set their own password (current) **or** temporary password with forced change (offered in the demo) |
| Officers who are also members | one account (current) **or** a separate admin login |
| WhatsApp receipt upload (QR-linked number) | deferred — separate commercial product |
| Online payment (e.g. Paystack) with automatic verification | future option, mentioned in the demo |

## Developer (non-code)

- Share the staging link, the `boba-staging` login and fresh officer links with Abiodun for a few technical testers.
- Production starts from a fresh database: no synthetic accounts or records.
- Handover documentation and architecture overview at completion.
- Scope and commitment-invoice call (Monday or Tuesday evening); invoice after the call.
