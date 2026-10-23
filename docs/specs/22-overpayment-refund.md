# 22 — Overpayment Receipt & Refund (Kelebihan Pembayaran & Pengembalian)

Status: Implemented 2026-10-23 (v1.3). Receipt/refund/cancellation, historical account capture, guardian-borne itemized bank fees, reconciliation/closing, private proof and role navigation implemented and verified. User-approved CoA 2-1200 and posting design; accountant confirmation remains required before go-live. Amends spec 14 with dedicated overpayment/refund records and the split-transfer recording procedure below; depends on implemented spec 21. Supersedes v1.2 (2026-10-23) and v1.1 (2026-10-18).

## Problem

Money can reach the school bank account that the system refuses to record as a payment. A guardian transfers for the same bill twice, or transfers Rp 3.000.000 for a Rp 300.000 bill. Spec 14 validation (AC-14-05) and spec 21 reject the upload because allocations must equal the amount and cannot exceed the bill's claimable balance. So the bank holds money that the ledger does not, and returning it today means a hand-written manual journal with no link to the guardian, the original payment, or proof. The books drift from the bank statement and the refund cannot be traced.

## Goals

- The treasurer records money received in excess, tied to a guardian and optionally a student, with proof of the bank mutation.
- The treasurer records the refund to the guardian, with proof and an audit trail.
- Both steps post balanced journals; the held excess is a visible liability until it is refunded.
- Month close proves that the guardian-deposit account agrees with the overpayment records as of the month end.

## Non-Goals

- Applying the excess to the guardian's other or future bills (credit balance). Spec 14 rejects deposits on student accounts; revisit as a separate decision.
- Partial settlement of the liability. v1 settles the entire held amount in one refund: net money to the guardian plus the guardian-borne bank fee equals the held amount. Retaining some credit for a later refund remains out of scope.
- Money from an unidentified sender. An overpayment needs a guardian; unidentified receipts stay outside this spec (a suspense account is a separate decision).
- A guardian-facing request or approval flow, or notifying the guardian (see open questions). The treasurer works from the bank statement and the guardian's own report.
- Payment gateway refunds or automated bank transfers. The refund itself happens at the bank; the system records it.
- Automatic splitting, changing a payment amount during verification, or using the cashier to disguise a bank transfer as cash. A wrongly verified payment is still corrected by void (spec 14); the manual split-transfer procedure below keeps existing validation and posting services.
- Blocking month or year-end close while an overpayment is held. A held excess at month end is a legitimate liability.

## User Stories

- As a treasurer, I want to record a double transfer as an excess received so that the bank and the ledger agree.
- As a treasurer, I want to record the refund with the bank reference so that the guardian's money is traceable.
- As a treasurer, I want to cancel an excess I recorded in error so that a typo does not stay in the books.
- As a principal, I want to see held excess amounts, their age and refund history so that I can confirm no guardian money is sitting unreturned.
- As an accountant, I want month close to reconcile the guardian-deposit account so that its balance is provably backed by overpayment records.

## Requirement Priorities

- **P0:** Immutable receipt/refund/cancel records, guardian-borne bank fees, historical account capture, per-account reconciliation/closing, split-transfer instructions, authorized proof access and clickable navigation. All acceptance criteria below are release requirements.
- **P1, deferred:** Guardian refund notifications and an optional separate approval workflow. Neither changes v1's role permissions.
- **P2, separate decisions:** Applying a held balance to future bills, partial liability settlements, unidentified receipts and bank/gateway integration.

## Model

An `overpayments` record moves `held` → `refunded`, or `held` → `cancelled`.

**Record excess received** (`finance` or `admin`):
- Fields: guardian (required), student (optional), amount (> 0), bank mutation date, posting date, deposit account (active, postable `asset`, `is_cash = true`, non-contra), reason (`double_payment`, `wrong_amount`, `other`), optional related payment, note, and required bank-mutation proof (JPG/PNG/PDF, max 5 MB, private storage, SHA-256 stored). Capture `liability_account_id` from the validated guardian-deposit setting and link the posted receipt journal. These stored facts are immutable.
- The student, if given, must be linked to the guardian. A related payment, if given, must be a verified bank transfer with at least one allocation, and every allocated student must be linked to the guardian. Validate these links at creation; later guardian/student relationship changes do not alter historical amounts or journal ownership. The related payment is context, not an allocation or authority to modify a bill.
- The bank mutation date is when the money arrived (for matching the bank statement); it may fall in a closed period. The posting date defaults to the mutation date and must fall in an open period; it must not be before the mutation date.
- Posts: Dr deposit account / Cr Titipan Wali, dated the posting date.
- Duplicate proof hash across overpayments or payments shows a warning, as in spec 14.

**Refund** (`finance` or `admin`, `held` only):
- Fields: refund date, paying account (active, postable, non-contra cash/bank asset), required bank reference, `bank_fee` (default 0), and optional proof (same MIME/size/private-storage rules as receipt proof).
- **Guardian-borne fee (user decision 2026-10-23):** Let `held_amount` be the immutable receipt amount, `bank_fee` the actual fee charged by the bank, and `net_refund = held_amount − bank_fee`. Require `0 ≤ bank_fee < held_amount`; the guardian must receive a positive net amount. The server derives net and total bank outflow. If a caller also submits either total, reject a value inconsistent with that calculation. No school bank-fee expense account or expense journal is introduced.
- The refund settles the full liability: Dr the receipt's captured Titipan Wali account `held_amount`; Cr the paying Bank account `net_refund` with memo "Dana bersih ke wali"; Cr the same Bank account `bank_fee` with memo "Biaya bank ditanggung wali". The two credits together equal `held_amount`. When the fee is zero, omit its line because the journal poster rejects zero-value lines. Store the fee and net amount on the refund facts, detail page and audit values. Do not credit Bank for the full held amount and then add another fee credit: that would double-count the fee.
- Example: held Rp 2.700.000, fee Rp 6.500, guardian receives Rp 2.693.500, bank outflow Rp 2.700.000. Record the actual fee from the bank evidence:

  | Account / memo | Debit | Credit |
  | --- | ---: | ---: |
  | 2-1200 Titipan Wali | Rp 2.700.000 | — |
  | Bank — Dana bersih ke wali | — | Rp 2.693.500 |
  | Bank — Biaya bank ditanggung wali | — | Rp 6.500 |
  | **Total** | **Rp 2.700.000** | **Rp 2.700.000** |

  The two credit rows use the same Bank account ID. Their memos distinguish the transfer and fee; no separate "guardian fee" CoA is created. A future school-borne policy would use one ordinary bank-administration expense CoA and a fee-payer field, not separate school/guardian expense accounts; that policy is outside this v1.
- The refund date must fall in an open period and must not be before the receipt's posting date. Persist the refund journal link, paying account, actor, bank reference, proof path/hash, fee, net amount and refund date atomically with `refunded`. The original receipt facts remain unchanged.

**Cancel** (`held` only, requires a reason):
- Posts a reversal of the receipt entry on the cancel date and sets `cancelled`. Used when the record was a mistake. A `refunded` or already `cancelled` overpayment cannot be cancelled. The requested cancellation date must be on or after the receipt posting date; enforce this before checking whether the requested period is open. After any move to the next open period, the effective reversal date must also be on or after receipt posting. Store both requested date and reversal journal link.
- Like a payment void, the reversal goes through `JournalPoster::reverse()`: if the cancel date's period is closed, it is posted on the first day of the next open period with the original date in the memo. Record and refund, which the treasurer dates by hand, are rejected in a closed period instead.

Nothing is edited after save. Corrections are new entries (spec 14 immutability). For all operations, a domain rejection posts no journal, changes no operational/audit row and leaves no newly uploaded orphan file. Existing receipt/refund proofs are retained. JSON/non-Inertia operation rejections use HTTP 422; browser form validation and Inertia error feedback follow existing finance conventions.

### Mixed transfer: bill portion plus excess

This is a manual two-record procedure, not a new split-payment service. For a bank transfer of Rp 3.000.000 intended to settle a Rp 300.000 bill:

1. The guardian uploads a bank-transfer payment allocating **only Rp 300.000** to that bill, attaches the bank proof for the gross transfer and notes that it is the bill portion of the Rp 3.000.000 transfer. This is an explicit amendment to spec 14's payment-header wording: in this documented split, `payments.amount` represents the allocated bill portion; the physical bank transfer is evidenced across that payment and the linked overpayment. Spec 21's claim limits still apply. A Rp 3.000.000 payment allocating Rp 300.000 remains invalid.
2. Finance checks the bank statement and verifies that Rp 300.000 payment through the existing verification flow. Verification never changes its amount and the cashier is not used. If the original upload was rejected for exceeding the bill balance, the guardian submits the correctly allocated portion; finance does not silently resize the rejected payment.
3. Finance records an overpayment of **Rp 2.700.000**, references the verified payment, uses the same bank proof, and notes the gross amount, accepted bill portion and bank reference. Its deposit account must match the payment journal's bank account, and its posting date must match that journal date for this split procedure. The two entries debit Bank for Rp 300.000 and Rp 2.700.000 respectively, totalling exactly Rp 3.000.000.
4. Reusing the proof for these linked portions can produce a duplicate warning; finance reviews it as evidence of the intentional split. It is never a reason to record another full Rp 3.000.000 receipt or skip the ordinary payment/claim checks. The overpayment does not allocate to the bill.
5. Refund only the held Rp 2.700.000 liability, less the guardian-borne fee. The bill portion stays paid. A separate double transfer after a bill is already settled is recorded in full as a new overpayment; its related payment identifies the original verified bill payment, not another debit to Bank.

This requires completion of both ordinary verification and overpayment recording by finance; v1 does not promise an atomic operation spanning the two records. If the guardian instead requests the **entire Rp 3.000.000 returned** before any bill portion is verified, finance may record and refund the entire transfer as an overpayment; the bill stays unpaid and a later correct payment is a separate bank transfer. Do not both record that entire transfer as held and verify a bill portion of the same receipt.

## Accounts & posting

- User-approved new default account **2-1200 Titipan Wali (Kelebihan Pembayaran)**: active, postable, non-contra `liability`, `is_cash = false`, added to `ChartOfAccountsSeeder`. No bank-administration expense account is required for the guardian-borne v1 policy.
- New nullable setting `guardian_deposit_account_id` (FK → `accounts`, `nullOnDelete`), added the same way as `default_transfer_account_id` and seeded to 2-1200. It is chosen on the admin school profile page and validated as an active, postable, non-contra `liability` account with `is_cash = false`, dedicated to this subledger. It must differ from `prepaid_revenue_account_id`; reciprocal configuration checks must preserve this separation. A later setting change affects only new receipts, never existing records or historical reports.
- While the setting is empty, the overpayments page shows a configuration notice and Record is disabled (server rejects with HTTP 422). Existing held records can still be refunded/cancelled using their captured liability account. A captured account must remain valid for posting: if inactive, show an actionable request for admin reactivation; do not substitute the current default. Cancellation reverses the exact original receipt lines, including its original deposit account.
- New `JournalSource` cases with labels: `overpayment_receipt` (Penerimaan Kelebihan Bayar), `overpayment_refund` (Pengembalian Kelebihan Bayar), `overpayment_cancellation` (Pembatalan Kelebihan Bayar). `JournalPoster::reverse()` maps `overpayment_receipt` → `overpayment_cancellation`; without that mapping it would fall back to `manual`.
- Each line carries the `student_id` when the overpayment names one. Guardian-deposit accounts are outside the receivables and prepaid-revenue subledgers.
- Receipt, refund and cancellation journal IDs are retained as immutable links. Operational foreign keys preserve the mandatory guardian (`restrictOnDelete`) and any related payment (`restrictOnDelete`); an optional student may become null on deletion without losing guardian, money or journal facts. A guardian cannot be deleted while historical overpayments reference them; return an explanatory UI rejection instead of a database error.
- **Lifecycle owns automatic journal corrections:** the general manual-journal reverse endpoint must reject all three overpayment sources and any reversal whose original source is an overpayment source (HTTP 422). Hide its reverse action and direct authorized users to the overpayment. Enforce this on the server, including forged requests and requests after refund/cancel; `JournalPoster::reverse()` remains callable from the transactional cancellation service. No existing payment/bill source rules are changed here.

## Reconciliation & closing

- **Guardian-deposit reconciliation**, as of a date D: for each liability account A, GL balance is Σ posted credits − posted debits dated ≤ D. Its subledger is Σ held amounts for receipts captured to A whose receipt journal date is ≤ D and whose refund/cancellation journal is absent or dated > D. Use journal dates even when the current status is refunded/cancelled; the bank fee does not reduce the liability amount included before settlement. A row-level refund/cancel timestamp is not an as-of cutoff. Define age as days from bank mutation date to D for the records held at D.
- Month close gains a new `ClosePrecondition` (registered through `PeriodService::addPrecondition()`, separate from the receivables precondition) that fails with HTTP 422 when this reconciliation has a non-zero difference at the month end. Year-end closing inherits it through the month-close precondition.
- The reconciliation report has a guardian-deposit section with one row per account, GL balance, subledger, difference and the list held at D with age. The account set is the union of every captured liability account in overpayments and the current configured account, even when inactive, cleared or fully settled today. Never hide old accounts when settings change or offset a positive difference on A with a negative difference on B: every account must reconcile independently. With no configured account and no history, show an unconfigured zero section and do not block unrelated closing.
- Held overpayments never block close by themselves.

## Pages

- **`/fees/overpayments`**: paginated list with status, guardian, optional student, held amount, mutation/posting dates and held age; filters by status and guardian; actions Record, Refund, Cancel; private proof preview. A visible detail action exposes journal links and all immutable receipt/refund/cancel facts. Refund confirmation states the held amount, fee borne by the guardian, exact net amount to the guardian and total bank outflow before submission. An empty list shows the Record action for writers and a read-only empty state for principal.
- The cash book and ledger show the entries with a link back to the overpayment.
- No guardian-facing page in v1.
- **Writer journey:** admin/finance login or role switch → Dashboard/sidebar → Kelebihan Pembayaran (`/fees/overpayments`) → Record/detail → Refund or Cancel → confirmation. Add the primary destination to the sidebar for both roles.
- **Principal journey:** principal login or role switch → Dashboard/sidebar → Kelebihan Pembayaran → detail/proof/journal. No mutation controls; forged writes return HTTP 403.
- **Configuration journey:** admin → user menu Settings → School Profile → guardian-deposit account setting. Finance/principal with missing configuration see a notice asking an admin to configure it, without an inaccessible settings link.
- **Audit/report journey:** authorized admin/finance/principal → sidebar General Ledger or Cash Book → journal entry → linked overpayment detail. The reconciliation page's guardian-deposit section links to the relevant details. Named routes/Wayfinder supply links; direct URL access alone is incomplete. Browser verification walks these paths for each role.

## Access

| Action | admin | finance | principal | teacher | parent | student |
|---|---|---|---|---|---|---|
| Record, refund, cancel | yes | yes | – | – | – | – |
| View list and proof | yes | yes | read | – | – | – |

Authorization follows spec 15's union of held roles; active role changes navigation only. A user who also holds parent retains finance/admin powers granted by those roles; a parent-only user gains none here.

Every mutation writes an audit row (spec 15): `overpayment_recorded`, `overpayment_refunded`, `overpayment_cancelled`.

## Acceptance Criteria

- **AC-22-01**: Recording Rp 2.700.000 with mutation and posting date 2026-10-20 into the bank account creates a `held` overpayment and one balanced entry Dr Bank 2.700.000 / Cr Titipan Wali 2.700.000 dated 2026-10-20, source `overpayment_receipt`.
- **AC-22-02**: Refunding it on 2026-10-22 with fee 0 sets `refunded`, stores bank reference/fee/net amount, and posts Dr Titipan Wali 2.700.000 / Cr Bank 2.700.000 dated 2026-10-22, source `overpayment_refund`; the guardian receives Rp 2.700.000 and the liability returns to 0.
- **AC-22-03**: A refund dated before the receipt's posting date returns HTTP 422. Refunding a `refunded` or `cancelled` overpayment returns HTTP 422.
- **AC-22-04**: Cancelling a `held` overpayment without a reason returns HTTP 422. With a reason it posts a reversal of the receipt entry with source `overpayment_cancellation` and sets `cancelled`; the original entry is unchanged.
- **AC-22-05**: Cancelling a `refunded` overpayment returns HTTP 422.
- **AC-22-06**: Recording or refunding with a posting date in a closed period returns HTTP 422 and posts nothing. Recording with a bank mutation date in a closed period and a posting date in an open period succeeds. Cancelling while the cancel date's period is closed posts the reversal on the first day of the next open period.
- **AC-22-07**: A recorded amount of zero or less, a missing guardian, a deposit account that is not `is_cash`, or a posting date before the mutation date returns HTTP 422.
- **AC-22-08**: A student not linked to the guardian, or a related payment allocating to a student not linked to the guardian, returns HTTP 422.
- **AC-22-09**: A proof whose SHA-256 matches another overpayment or a payment shows a duplicate warning but does not block saving.
- **AC-22-10**: With `guardian_deposit_account_id` empty, Record returns HTTP 422 and the page shows the configuration notice. Saving the setting to a non-liability or non-postable account returns HTTP 422.
- **AC-22-11**: Received 2026-10-20 and refunded 2026-11-05: the guardian-deposit reconciliation as of 2026-10-31 shows the Rp 2.700.000 held on both sides with zero difference, and October closes successfully.
- **AC-22-12**: A Titipan Wali balance not backed by overpayment records (e.g. a manual journal posted to that account) makes the reconciliation difference non-zero and closing that month returns HTTP 422.
- **AC-22-13**: After any sequence of receipt, refund and cancel, the guardian-deposit reconciliation difference is 0, the trial balance balances, and the receivables reconciliation difference is 0.
- **AC-22-14**: Overpayments never change any bill's `paid_amount` or `status`.
- **AC-22-15**: Record, refund and cancel each write one audit row. A principal can open the list and proof but record, refund and cancel return HTTP 403; teacher, parent and student receive HTTP 403.
- **AC-22-16**: Two concurrent refunds of the same overpayment leave exactly one refund entry and one refund audit; the loser receives HTTP 422 with no state/journal addition (pgsql).
- **AC-22-17**: Held Rp 2.700.000, bank fee Rp 6.500: refund records net Rp 2.693.500 and bank outflow Rp 2.700.000. The three-line journal debits the captured liability Rp 2.700.000, credits Bank Rp 2.693.500 for net refund and credits the same Bank account Rp 6.500 for the fee, with distinct memos. There is no school expense, no separate guardian-fee CoA and no further credit on top of that total. List/detail/confirmation and audit expose the same fee and net values.
- **AC-22-18**: Negative fee, fee equal to or greater than held amount, or a manipulated client net amount inconsistent with the server calculation returns HTTP 422 and posts nothing. Zero fee succeeds with exactly two journal lines; no zero-value fee line is posted. The server computes persisted net/bank totals instead of accepting caller totals.
- **AC-22-19**: A receipt uses account A, then admin changes the default to B or clears it. Refund/cancel still settles A; reconciliation as of a date before settlement reports the original amount under A and zero under B. Earlier reports remain identical after the change. An invalid/inactive captured account yields an actionable rejection; it never silently switches to B.
- **AC-22-20**: A configured contra liability, cash account or prepaid-revenue account returns HTTP 422. A manual journal posted to a previously used guardian-deposit account is still detected after the default changes, even if every overpayment on the old account is now settled. Opposite differences on two accounts do not cancel each other for closing.
- **AC-22-21**: Cancelling with a requested date before receipt posting returns HTTP 422 even if that requested period is closed. A valid cancel moved to the next open period retains both requested and effective dates and never predates receipt.
- **AC-22-22**: Manual-journal reverse requests against receipt/refund/cancellation journals return HTTP 422, including forged requests after refund/cancel. No extra entry is posted and the overpayment remains unchanged. The cancellation service can still post exactly one authorized reversal.
- **AC-22-23**: For a gross transfer Rp 3.000.000, the documented ordinary bank-transfer payment of Rp 300.000 is verified, then a linked overpayment Rp 2.700.000 is recorded on the same bank account and journal date. Together they debit Bank exactly Rp 3.000.000, the bill is paid Rp 300.000, and the held liability is Rp 2.700.000. The shared proof produces a review warning, not an extra debit. Refunding with fee Rp 6.500 sends Rp 2.693.500 to the guardian, credits Bank Rp 2.700.000 in the school journal and leaves the bill paid.
- **AC-22-24**: A refund racing a cancellation has exactly one terminal outcome and settlement journal, with one corresponding audit row; the other operation returns HTTP 422 (pgsql). Repeating either completed action leaves journals, proof references and audit counts unchanged.
- **AC-22-25**: All writer/principal journeys in Pages are clickable from the appropriate sidebar/settings entry. Principal proof/detail/report links work, with no write controls; unauthorized direct proof and mutation requests return HTTP 403 without exposing proof contents or refund state. Multi-role capability follows spec 15.
- **AC-22-26**: Deleting a guardian with overpayment history is rejected with explanatory feedback and preserves all records. Later removal of guardian/student links does not change prior receipt/refund values or as-of reconciliation. Missing settings with no history do not block closing; clearing settings with history does not skip its checks.
- **AC-22-27**: A failed receipt/refund request leaves no new proof file, journal or audit row. Successful cancellation retains the original proof; refund proof, when provided, is privately authorized with the same size/MIME restrictions as receipt proof.

## Constraints & Assumptions

- IDR, `decimal(15,2)`, same posting service and transaction rules as spec 14: posting runs in the same database transaction as the operational change, and the `held` row is locked `FOR UPDATE` before refund or cancel.
- The test suite runs on sqlite, which has no `FOR UPDATE`. AC-22-16 and AC-22-24 must be proven with real contention on pgsql (`sail exec -e DB_CONNECTION=pgsql -e DB_DATABASE=testing -e DB_HOST=pgsql laravel.test php vendor/bin/phpunit …`).
- The proof file is stored on the private disk with an opaque name, like payment proofs.
- The user approved account 2-1200 and these posting rules for technical planning on 2026-10-23. Treasurer/accountant confirmation remains a go-live gate, per spec 14; it does not block drafting or reviewing the implementation plan.
- No schema change to `payments`, `payment_allocations` or `bills`; the mixed-transfer procedure amends their documented interpretation only. Receipt/refund/cancel writes, journals and audits share one transaction, with terminal eligibility rechecked under the overpayment lock before posting.
- Pre-existing, not changed here: `PeriodService::close()` runs its preconditions outside the closing transaction and without a lock, so a posting committed between the check and the close is not caught. This applies to every precondition, including the new one.

## Success Metrics

- **Release gate:** all ACs pass, including SQLite owner tests, PostgreSQL contention, per-account reconciliation after setting changes and the documented role journeys. Every generated journal balances exactly in decimal-string arithmetic.
- **First-month review:** target zero confirmed duplicate settlements, unauthorized proof access or guardian-deposit reconciliation differences. Review the operational/audit records and reconcile at month end. No existing baseline or support-volume reduction is claimed; no new analytics system is required.

## Timeline & Dependencies

- Specs 21 and 22 are implemented. Verification evidence is recorded in `docs/plans/2026-10-23-overpayment-refund.md`. Existing installations must migrate and configure the dedicated liability account before new receipts; accountant confirmation remains required before real transactions/go-live.
- Existing specs 14, 15 and 21 supply posting, permissions, audit, portal upload and claim limits. No dependency on a payment gateway or new notification type.

## Open Questions

- `[GO-LIVE GATE — Treasurer/accountant: Account and posting review]` — confirm the deployment account mappings, historical-account settlement/reconciliation, guardian-borne fee treatment and manual split-transfer procedure before real transactions. User approval on 2026-10-23 permits technical planning with this baseline; the fee-payer decision is settled.
- `[NON-BLOCKING — Product/treasurer: Guardian notice]` — consider a WhatsApp/in-app refund notice as a later spec 17 extension. No refund notification in v1.
- `[NON-BLOCKING — School administration: Separation of duties]` — consider distinct recorder/refunder approval in a later version. V1 retains existing admin/finance capability for both actions; no self-refund prohibition is added implicitly.
