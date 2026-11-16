# Treasurer, verifier and executive guide

This guide is for the Treasurer, the Payment Verifier and members of the Executive who oversee the association's finances. It explains how to set up dues and levy categories, charge members through assessments, record and verify payments, allocate money to what members owe, make corrections, read member statements and reports, import approved historical records, and carry out the month-end reconciliation. Button names, page names and field labels are written exactly as they appear on screen.

> All names, membership numbers and amounts in this guide are synthetic examples. Official dues and levy amounts, role assignments and finance policies (verification rules, reversal authority, allocation priority, refunds) still await the association's approval (see `docs/23-client-decisions.md`). The platform does not collect money online; it records money received by bank transfer, cash or POS.

## Who can do this

| Task | Role(s) that can do it |
|---|---|
| View **Finance overview**, **Payments**, **Assessments**, categories, **Reports and reconciliation**, member ledgers | Treasurer, Payment Verifier, Executive (read-only oversight) |
| Create and edit **Dues and levy categories** | Treasurer |
| Create, preview, post or discard assessments | Treasurer |
| **Record a payment** | Treasurer |
| **Verify payment** or **Reject payment** | Payment Verifier |
| **Record allocation** | Treasurer |
| **Record adjustment** (waiver, correction, increase, cancellation) | Treasurer |
| **Reverse allocation** and **Reverse payment** | Payment Verifier |
| Download statements and report CSV files | Treasurer, Executive (read-only oversight) |
| **Historical records** import | Treasurer (a second officer holding the same access must confirm) |

The Treasurer cannot verify payments or reverse them; the Payment Verifier cannot record, allocate or adjust. The System Administrator has no access to finance pages.

## Key ideas

- Money is never "edited". Posted charges, payments, allocations, adjustments and reversals are permanent. Mistakes are corrected with an adjustment or a reversal, and the history is kept.
- A recorded payment counts for nothing until it is verified.
- Only verified money can be allocated, and only to the same member's charges.

### What each figure means

These definitions are used identically on every page, statement and CSV file (from `app/Services/Finance/LedgerQuery.php`):

| Figure | Definition |
|---|---|
| Assessed | Σ posted obligations |
| Adjustments | Σ signed obligation adjustments |
| Effective obligations | assessed + adjustments |
| Allocated | Σ active allocations |
| Outstanding | effective obligations − allocated (never negative) |
| Verified payments | Σ payments with status "verified" (reversed payments excluded) |
| Unallocated credit | verified payments − allocated (never negative) |
| Net position | outstanding − unallocated credit = effective obligations − verified payments |
| Pending | Σ payments awaiting verification (not counted anywhere above) |

A positive position is an amount owed to the association; a negative position is a credit in the member's favour.

## Dues and levy categories

Categories label assessments; amounts are set on each assessment.

1. Open **Dues and levy categories**.
2. Under **New category**, enter **Code** (for example DUES or LEVY-HALL), **Name**, **Kind** (**Dues**, **Levy** or **Other**), optionally **Suggested amount (₦, optional)** and **Description**.
3. Press **Create category**.
4. To change a category, press **Edit** on its row, make the change and press **Save**. Untick **Active (available for new assessments)** to stop it being used for new assessments.

## Creating an assessment

An assessment charges an amount to members. It is saved as a draft, previewed and then posted once.

1. Open **Assessments** and press **New assessment**.
2. Choose the **Category**, and enter **Title** (for example "Annual dues 2027") and **Amount per member (₦)**.
3. Enter a **Period label (optional)** such as 2027. This is used to warn you if members were already charged in the same category for the same period.
4. Set the **Effective date** and, if needed, **Due date (optional)** and **Description (optional)**.
5. Under **Who is charged**, choose **All active members** or **Only the members selected below** and tick members under **Select members**.
6. Press **Save draft and preview**.
7. Check the **Preview before posting**: **Members to be charged**, **Amount each**, **Total to be assessed** and **Excluded (not active)**. Read any warning headed "Already charged in this category for period …" and decide whether it would be a duplicate charge. Open **Members to be charged** to see the list.
8. If anything is wrong, press **Discard draft** and start again. Drafts cannot be edited.
9. If everything is right, tick **I have checked the member list, amount and total.** and press **Post to N members**, then confirm.

Posting creates one charge (obligation) for each listed member. A posted assessment cannot be edited or posted twice. If the active-member list changes between preview and posting, posting is refused; reload the page and review again.

## Recording a payment

1. Open **Payments** and press **Record a payment** (or press **Record a payment** on a member ledger).
2. Choose the **Member**.
3. Enter **Amount received (₦)** (for example 20000 or 20,000.00) and **Date received** (not in the future).
4. Choose **Method**: **Bank transfer**, **Cash**, **POS / card terminal** or **Other**.
5. Enter the **Bank or receipt reference** from the bank statement or receipt book. Each reference can be recorded only once in the whole system, including on payments that were later rejected, so check it carefully.
6. Optionally fill in **Name on the payment (if different)** and **Note**.
7. Optionally attach **Evidence (optional)**: a teller, transfer receipt or cash receipt as PDF, JPEG or PNG (PDF up to 10 MB, images up to 5 MB). PDFs containing scripts or embedded files are refused; print them to a plain PDF first.
8. Press **Record payment**.

If a payment with the same member, amount and date already exists, you are stopped. Check the earlier record. Only if this is genuinely a different payment, tick **This is a separate payment, even if the same amount was received from this member on the same day.** and press **Record payment** again.

The payment now shows as **Pending** ("Awaiting verification") and does not count towards any balance.

## Verifying or rejecting a payment

Verification is done by a different officer from the one who recorded the payment while the setting **A different officer must verify each recorded payment** is on (the default; it applies to payments recorded after any change).

1. Press the **Payments awaiting verification** tile on the **Overview** page, or open a payment under **Payments awaiting verification** on **Finance overview** (or filter **Payments** by **Awaiting verification**).
2. Check the amount, date and reference against the bank statement or cash book. Use **Download** to view any evidence.
3. To accept it, optionally write a **Verification note (optional)**, tick **I have checked this payment against the bank statement or cash record.** and press **Verify payment**.
4. To refuse it, write the **Reason for rejection** and press **Reject payment**. A rejected payment stays on record but never counts.

If you recorded the payment yourself, the page says a different officer must verify it.

## Allocating verified money

Allocation applies a verified payment to the member's outstanding charges.

1. Open the verified payment. The **Funds** panel shows **Amount**, **Allocated** and **Available to allocate**.
2. Under **Allocate to this member's dues and levies**, type an amount in **Allocate (₦)** against each charge you want to pay.
3. Press **Record allocation**.

Rules and common cases:

- **Partial payment:** allocate less than the outstanding amount; the rest stays outstanding.
- **One payment across several charges:** fill in more than one row before pressing **Record allocation**.
- **Several payments on one charge:** allocate from each payment in turn.
- No row may exceed its outstanding amount, and the total may not exceed **Available to allocate**.
- **Suggest oldest first** fills the boxes from the oldest charge forward. It is only a suggestion: the association has not yet approved an allocation priority policy, so the officer decides the order. Review the figures before pressing **Record allocation**.
- Any amount left unallocated stays as the member's **unallocated credit**. When a later assessment is posted, open the payment again and allocate the remainder. If the member has nothing outstanding the page says so.

## Adjusting a charge

Adjustments are made on the member ledger (see below), in the **Obligations** table.

1. Press **Adjust** on the charge.
2. Choose **Type**:
   - **Waiver (reduce)**: enter a positive amount to reduce the charge.
   - **Correction (+ or −)**: enter an amount; put a minus sign in front to reduce.
   - **Increase**: enter a positive amount to raise the charge.
   - **Cancel remaining balance**: removes whatever is still outstanding; no amount needed.
3. Set the **Effective date** and write the **Reason**.
4. Press **Record adjustment** and confirm. It cannot be edited afterwards.

A charge can never be reduced below zero or below what has already been paid towards it. To reduce it further, the related allocation must first be reversed by the Payment Verifier.

## Reversing an allocation

Payment Verifier only.

1. Open the payment. In the **Allocations** table, press **Reverse** on an active allocation.
2. Write the **Reason** and press **Reverse allocation**, then confirm.
3. The amount returns to the payment's **Available to allocate** and can be allocated again.

## Reversing a whole payment

Payment Verifier only. Use this only when a verified payment should never have counted, for example a bounced transfer or a recording error. A payment still awaiting verification is rejected instead.

1. Open the verified payment and go to **Reverse this payment**.
2. Write the **Reason** and tick **I confirm this payment should be reversed.**
3. Press **Reverse payment** and confirm.

All active allocations of the payment are reversed first, then the payment. The original record, the allocations and the reversals remain visible in the **Reversals** table and on the member's statement.

## Member ledger and statements

Open a member ledger from **Member balances** on the reports page (press the membership number), from **Open financial record** on a member record, or from **Member ledger** on a payment.

- The top shows **Effective obligations**, **Allocated**, **Outstanding**, **Verified payments**, **Unallocated credit** and **Awaiting verification**.
- **Obligations** lists each charge with **Original**, **Adjustments**, **Allocated** and **Outstanding**. **Payments** lists every payment and its status.
- Under **Statement**, set **From** and **To** and press **Show period** (the default is the last twelve months). The statement shows the **Opening position**, **Charges in period**, **Payments in period** and **Closing position**, each transaction with its running **Position**, and **Position at** the end date split into **Outstanding dues and levies** and **Unallocated credit**. Payments awaiting verification are listed underneath but not included.
- **Download statement CSV** gives the same lines with opening and closing positions.

## Reports and reconciliation

Open **Reports and reconciliation**. Set **From** and **To** and press **Show period** (the default is 1 January of this year to today).

- **Position today (all time):** the figures defined above, plus **Payments reversed (all time)**.
- **Dues and levy performance:** charges whose effective date falls in the period, by category, with **Collected** = allocated ÷ effective obligations. **Export CSV** downloads it.
- **Collections in period:** verified payments by date received, including payments later reversed, by method, and the payment reversals recorded in the period. **Export collections CSV** downloads the payments.
- **Payments awaiting verification:** everything still pending.
- **Reconciliation checks:** nine automatic checks. Every row must show **No exceptions**.
- **Member balances:** search by **Member** and choose **Show** (**All members**, **With outstanding balances**, **With unallocated credit**), then **Apply**. **Export all balances CSV** downloads every member, including net position.
- **Adjustments in period** and **Reversals in period:** each with its reason and officer.

## Historical records import

Open **Historical records** (Treasurer). Import only records the association has approved, with a documented source. Never invent opening balances, past payments, dates or references. Ask the System Administrator to take a backup first.

Choose the right kind:

- **Historical transactions** — individual charges and payments, each traceable to a source document. Template columns: record_type (obligation or payment), membership_number, date, amount_ngn, category_code (for obligations; must match an active category code), external_reference (required for payments; must not already exist), description. Imported payments are recorded as verified and left unallocated for an officer to allocate.
- **Opening balances** — amounts owed at an approved cut-off date when detailed history is unavailable. Template columns: membership_number, amount_ngn, description. You also give the **Approved cut-off date** and a **Category label**. Credit (negative) balances are refused; support them with real historical payment records instead.

Steps:

1. Press **Download template**, fill it in from the approved source and save as CSV.
2. Choose the **CSV file**, enter the **Approved source reference** (the minute, signed schedule or audit reference) and press **Preview**.
3. Check the row messages and compare **Totals of valid rows** with the approved source.
4. If any row is invalid or duplicate, press **Cancel preview**, correct the file and upload again.
5. A **different officer** who also has **Historical records** access opens the batch, ticks **I confirm the source records are approved and the totals above have been reconciled.** and presses **Complete import**. (The uploader cannot complete it while the two-officer setting is on.)

The same file cannot be imported twice, and pressing **Complete import** again changes nothing.

## Month-end reconciliation routine

1. On **Payments**, filter **Awaiting verification**. Make sure every payment received in the month has been recorded, and is verified or rejected.
2. On **Reports and reconciliation**, set the month as the period. Compare **Collections in period** and the method totals with the bank statement and cash book.
3. Under **Member balances**, choose **With unallocated credit** and allocate any verified money that should be applied.
4. Check that every **Reconciliation checks** row shows **No exceptions**.
5. Read **Adjustments in period** and **Reversals in period**; each should have a clear reason.
6. Download **Export CSV**, **Export collections CSV** and **Export all balances CSV** for the month's records.
7. Ask the System Administrator to create a backup and pass it to the custodian.

## If something goes wrong

- **A reconciliation exception appears** (a red "Reconciliation exceptions found" banner, an Overview tile, or a check without **No exceptions**): stop. Do not try to fix it by editing or by entering balancing records. Tell the Treasurer and the Executive, and escalate to the technical maintainer with the check name and the time you saw it. Once the cause is known, corrections are made only through adjustments or reversals.
- **A payment was recorded with wrong details:** if still pending, the Payment Verifier rejects it with the reason; if already verified, the Payment Verifier reverses it. You can then record it again correctly. A **rejected** payment releases its bank or receipt reference, so the corrected record can use the real reference. A **reversed** payment keeps its reference (it once counted); never invent a substitute reference — ask the technical maintainer if a reversed payment must be re-entered.
- **Money was allocated to the wrong charge:** the Payment Verifier reverses that allocation, then you allocate again.
- **A charge is wrong:** use **Record adjustment**; never post a second assessment to cancel the first.
- **You see an error you do not understand:** take a note of the exact message and contact the technical maintainer before trying again.
