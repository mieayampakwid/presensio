# 22 — Overpayment Receipt & Refund (Kelebihan Pembayaran & Pengembalian)

Status: Draft v1.1 (2026-10-18) — Ready for planning after accountant review of the new account, the posting rules and the refund-fee question. Amends spec 14: replaces the non-goal "cash refunds are a manual journal in v1" with a dedicated, traceable record. Related: spec 21 (blocks over-claiming uploads, so excess money can no longer be recorded as a payment). v1.1 (2026-10-18, review against the code): a held overpayment no longer blocks month close; the close precondition is an as-of reconciliation of the guardian-deposit account instead; cancel reversal gets its own source type; setting follows the `default_transfer_account_id` precedent; flat URL; guardian/student/payment link validation; bank mutation date stored separately from the posting date; refund bank fee moved to open questions.

## Problem

Money can reach the school bank account that the system refuses to record as a payment. A guardian transfers for the same bill twice, or transfers Rp 3.000.000 for a Rp 300.000 bill. Spec 14 validation (AC-14-05) and spec 21 reject the upload because allocations must equal the amount and cannot exceed the bill's claimable balance. So the bank holds money that the ledger does not, and returning it today means a hand-written manual journal with no link to the guardian, the original payment, or proof. The books drift from the bank statement and the refund cannot be traced.

## Goals

- The treasurer records money received in excess, tied to a guardian and optionally a student, with proof of the bank mutation.
- The treasurer records the refund to the guardian, with proof and an audit trail.
- Both steps post balanced journals; the held excess is a visible liability until it is refunded.
- Month close proves that the guardian-deposit account agrees with the overpayment records as of the month end.

## Non-Goals

- Applying the excess to the guardian's other or future bills (credit balance). Spec 14 rejects deposits on student accounts; revisit as a separate decision.
- Partial refunds. v1 refunds the held amount in full; if the guardian is owed less, record only the excess. (The refund bank fee is an open question.)
- Money from an unidentified sender. An overpayment needs a guardian; unidentified receipts stay outside this spec (a suspense account is a separate decision).
- A guardian-facing request or approval flow, or notifying the guardian (see open questions). The treasurer works from the bank statement and the guardian's own report.
- Payment gateway refunds or automated bank transfers. The refund itself happens at the bank; the system records it.
- Changing verification, void or the cash payment flow. A wrongly verified payment is still corrected by void (spec 14).
- Blocking month or year-end close while an overpayment is held. A held excess at month end is a legitimate liability.

## User Stories

- As a treasurer, I want to record a double transfer as an excess received so that the bank and the ledger agree.
- As a treasurer, I want to record the refund with the bank reference so that the guardian's money is traceable.
- As a treasurer, I want to cancel an excess I recorded in error so that a typo does not stay in the books.
- As a principal, I want to see held excess amounts, their age and refund history so that I can confirm no guardian money is sitting unreturned.
- As an accountant, I want month close to reconcile the guardian-deposit account so that its balance is provably backed by overpayment records.

## Model

An `overpayments` record moves `held` → `refunded`, or `held` → `cancelled`.

**Record excess received** (`finance` or `admin`):
- Fields: guardian (required), student (optional), amount (> 0), bank mutation date, posting date, deposit account (`is_cash`, usually the bank account), reason (`double_payment`, `wrong_amount`, `other`), optional related payment, note, and bank-mutation proof (JPG/PNG/PDF, max 5 MB, private storage, SHA-256 stored).
- The student, if given, must be linked to the guardian. The related payment, if given, must allocate only to students linked to the guardian.
- The bank mutation date is when the money arrived (for matching the bank statement); it may fall in a closed period. The posting date defaults to the mutation date and must fall in an open period; it must not be before the mutation date.
- Posts: Dr deposit account / Cr Titipan Wali, dated the posting date.
- Duplicate proof hash across overpayments or payments shows a warning, as in spec 14.

**Refund** (`finance` or `admin`, `held` only):
- Fields: refund date, paying account (`is_cash`), bank reference, optional proof.
- Posts: Dr Titipan Wali / Cr paying account, dated the refund date. The refund date must fall in an open period and must not be before the receipt's posting date.

**Cancel** (`held` only, requires a reason):
- Posts a reversal of the receipt entry on the cancel date and sets `cancelled`. Used when the record was a mistake. A `refunded` overpayment cannot be cancelled.
- Like a payment void, the reversal goes through `JournalPoster::reverse()`: if the cancel date's period is closed, it is posted on the first day of the next open period with the original date in the memo. Record and refund, which the treasurer dates by hand, are rejected in a closed period instead.

Nothing is edited after save. Corrections are new entries (spec 14 immutability).

## Accounts & posting

- New default account **2-1200 Titipan Wali (Kelebihan Pembayaran)**, a postable `liability` account, added to `ChartOfAccountsSeeder`.
- New nullable setting `guardian_deposit_account_id` (FK → `accounts`, `nullOnDelete`), added the same way as `default_transfer_account_id` and seeded to 2-1200. It is chosen on the school profile settings page and validated in `UpdateSchoolProfileRequest` as an active, postable `liability` account.
- While the setting is empty, the overpayments page shows a configuration notice and Record is disabled (the server returns HTTP 422), as the guardian portal does for the transfer account.
- New `JournalSource` cases with labels: `overpayment_receipt` (Penerimaan Kelebihan Bayar), `overpayment_refund` (Pengembalian Kelebihan Bayar), `overpayment_cancellation` (Pembatalan Kelebihan Bayar). `JournalPoster::reverse()` maps `overpayment_receipt` → `overpayment_cancellation`; without that mapping it would fall back to `manual`.
- Each line carries the `student_id` when the overpayment names one. This account is not part of the receivables subledger.

## Reconciliation & closing

- **Guardian-deposit reconciliation**, as of a date D: the GL balance of the guardian-deposit account at D equals Σ amounts of overpayments whose receipt entry is dated ≤ D and whose refund or cancellation entry is absent or dated after D. Dates are journal entry dates, not record timestamps, so a closed month reconciles the same way later.
- Month close gains a new `ClosePrecondition` (registered through `PeriodService::addPrecondition()`, separate from the receivables precondition) that fails with HTTP 422 when this reconciliation has a non-zero difference at the month end. Year-end closing inherits it through the month-close precondition.
- The reconciliation report shows the guardian-deposit check as its own section: GL balance, overpayment subledger, difference, and the list of overpayments held at D with their age in days.
- Held overpayments never block close by themselves.

## Pages

- **`/fees/overpayments`**: list with status, guardian, student, amount, bank mutation date, age in days; filters by status and guardian; actions Record, Refund, Cancel; proof preview.
- The cash book and ledger show the entries with a link back to the overpayment.
- No guardian-facing page in v1.

## Access

| Action | admin | finance | principal | teacher | parent | student |
|---|---|---|---|---|---|---|
| Record, refund, cancel | yes | yes | – | – | – | – |
| View list and proof | yes | yes | read | – | – | – |

Every action writes an audit row (spec 15): `overpayment_recorded`, `overpayment_refunded`, `overpayment_cancelled`.

## Acceptance Criteria

- **AC-22-01**: Recording Rp 2.700.000 with mutation and posting date 2026-10-20 into the bank account creates a `held` overpayment and one balanced entry Dr Bank 2.700.000 / Cr Titipan Wali 2.700.000 dated 2026-10-20, source `overpayment_receipt`.
- **AC-22-02**: Refunding it on 2026-10-22 sets `refunded`, stores the bank reference, and posts Dr Titipan Wali 2.700.000 / Cr Bank 2.700.000 dated 2026-10-22, source `overpayment_refund`; the Titipan Wali balance returns to 0.
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
- **AC-22-16**: Two concurrent refunds of the same overpayment leave exactly one refund entry (pgsql).

## Constraints & Assumptions

- IDR, `decimal(15,2)`, same posting service and transaction rules as spec 14: posting runs in the same database transaction as the operational change, and the `held` row is locked `FOR UPDATE` before refund or cancel.
- The test suite runs on sqlite, which has no `FOR UPDATE`. AC-22-16 must be proven on pgsql (`sail exec -e DB_CONNECTION=pgsql -e DB_DATABASE=testing -e DB_HOST=pgsql laravel.test php vendor/bin/phpunit …`).
- The proof file is stored on the private disk with an opaque name, like payment proofs.
- Treasurer and accountant must confirm account 2-1200 and the posting rules before go-live, per the spec 14 caveat.
- No change to `payments`, `payment_allocations` or `bills`.
- Pre-existing, not changed here: `PeriodService::close()` runs its preconditions outside the closing transaction and without a lock, so a posting committed between the check and the close is not caught. This applies to every precondition, including the new one.

## Open Questions

- `[NEEDS DECISION: Refund bank fee]` — an interbank refund usually costs a fee (e.g. Rp 6.500). Who bears it? (a) The school: the refund entry gains Dr Beban Administrasi Bank (a new setting for the expense account) and the guardian receives the full held amount. (b) The guardian: the bank pays out less than the held amount, so a fee line credits the paying account and the "full refund" rule needs an explicit fee field. Accountant to decide before planning.
- `[NEEDS DECISION: Guardian notice]` — should the guardian get a WhatsApp/in-app message when the refund is recorded? It would need a new notification type in spec 17 (`overpayment_refunded`). Proposed: yes, as a follow-up, not v1.
- `[NEEDS DECISION: Separation of duties]` — should the person who recorded an excess be barred from recording its refund? Same open question as spec 14.
