# 21 — Payment Return Workflow (Pembatalan & Batas Klaim Transfer)

Status: Draft v1.1 (2026-10-18) — Ready for planning. Follow-up to spec 14 (Fees C), deferred by the user on 2026-10-18 after the Fees C browser click-through. Amends spec 14 "Payment status" and the Guardian Portal upload rules; does not change the ledger. v1.1 (2026-10-18, review against the code): the claim check is an explicit validator mode instead of a blanket rule, verify re-allocation respects claims, Goal 3 narrowed to what the rules guarantee, any linked guardian may cancel (user decision), upload-race AC added.

## Problem

A guardian can upload several pending transfers for the same bill. The per-bill limit in spec 14 (`allocation ≤ net − paid`) counts only verified payments, so two pending transfers can each claim the full remaining balance. The treasurer verifies the first; verifying the second then fails the limit check and the only way out is to reject a transfer the guardian made in good faith. A guardian who uploaded the wrong proof or amount also has no way to withdraw it; only the school can reject it.

## Goals

- A guardian cannot create pending transfers that together claim more than a bill's remaining balance.
- A guardian linked to the students of a pending transfer can withdraw it without involving the school.
- A guardian upload or a verification re-allocation can never make another pending transfer fail verification.

## Non-Goals

- Changing posting rules, void, or the cash payment flow. A pending or cancelled transfer posts nothing, as today.
- Editing a pending transfer (cancel and re-upload instead).
- Cancelling a `verified` payment. Corrections stay void-only, by `finance` or `admin` (spec 14).
- Notifying anyone about a cancellation, including the submitting guardian when another linked guardian cancels. The portal history shows it.
- A new payment status. A cancelled transfer is stored as `rejected`, so reports, the portal history and queries need no change.
- Guarding school actions against pending claims (cash payment, discount increase, bill cancel or write-off). These can still make a pending transfer unverifiable; the treasurer resolves it by rejecting, or the guardian by cancelling. See the open question on the cashier.

## Rules

1. **Claimable balance.** For a bill and a payment being checked, `claimable = net − paid − Σ allocations of other pending payments`, where `paid` counts verified payments only (as today) and "other" excludes the payment being checked. Pending payments from any guardian count (a bill can be shared by two guardians of one child).
2. **Where it applies.** The claim check is an explicit mode of `AllocationValidator`, not its default:
   - Guardian transfer upload (`PaymentService::submitTransfer`): every allocation ≤ `claimable`.
   - Verification with a re-allocation (`PaymentService::verify` with a non-null re-allocation): every new allocation ≤ `claimable`, excluding the payment being verified.
   - Verification without re-allocation and cash payments (`createCash`) keep today's check against verified payments only.
3. **Violation.** An upload or re-allocation that exceeds `claimable` on any bill is a `PaymentOperationException`. The upload returns HTTP 422 on the `allocations` field naming the bill and its claimable amount, and the controller deletes the stored proof (as it already does for validation failures). The upload message tells the guardian that an earlier transfer is waiting for verification and can be cancelled from the payment history.
4. **Who may cancel.** A guardian may cancel a payment when they are linked to every student whose bill the payment allocates (the same rule that lets a guardian see the payment and its receipt in the portal), and the payment is a `bank_transfer`. Checks run in this order: role and link first (HTTP 403, without revealing the payment's status), then status (HTTP 422 if not `pending`).
5. **Effect of cancel.** In one transaction: lock the payment, re-check `pending`, set `status = rejected` and `reject_reason = 'Dibatalkan oleh wali'`, recompute the affected bills, write an audit row with action `cancelled_by_guardian` and the acting user. No `payment_rejected` notification. The proof file is kept, as for any rejected payment. Cancel is its own service method and does not call `reject()`, because `reject()` notifies.
6. **Lock order.** Upload locks the allocated bills (`ORDER BY id … FOR UPDATE`) before summing pending allocations, inside the upload transaction; it holds no payment lock. Verify, reject and cancel lock the payment first, then bills ordered by id (the existing `PaymentService` order). Two uploads for one bill therefore serialize on the bill lock, and the second sees the first's committed allocations.
7. **Races.** Cancel, verify and reject all re-check `pending` under the payment lock (`lockPending`). Whichever commits second gets the "no longer pending" 422.
8. **Existing overlaps.** Pending transfers that already over-claim a bill when this ships are not migrated. The first one verified succeeds as today; the rest fail verification and are rejected by the treasurer or cancelled by a guardian.

## Portal UI (`/my-fees`)

- Each bill carries `claimable` (`remaining − pending allocations`). The upload modal shows the claimable amount instead of the raw remaining balance; bills with `claimable = 0` are not selectable and carry the "menunggu verifikasi" flag.
- Each payment in the history carries `can_cancel`, computed on the server from rule 4 and `pending`. A "Batalkan" action with a confirmation dialog shows only when it is true. After cancel, the row shows as rejected with the reason "Dibatalkan oleh wali", and the bill's "menunggu verifikasi" flag clears if no other pending payment remains.
- The student read-only page has no cancel (students never upload).

## Access

| Action | admin | finance | principal | teacher | parent | student |
|---|---|---|---|---|---|---|
| Cancel a pending transfer | – | – | – | – | linked to all its students | – |

Admins can open the parent routes (`role:parent,admin`), but cancel is parent-only. Admin and finance keep using Reject in the verification queue. The queue lists pending payments only, so a cancelled transfer leaves the queue and shows in the payments list (`/fees/payments`, status filter "rejected") with the guardian-cancel reason.

## Acceptance Criteria

Amounts are whole rupiah.

- **AC-21-01**: A bill with net Rp 350.000, paid Rp 0 and one pending transfer of Rp 350.000: a second upload allocating any amount to that bill returns HTTP 422, creates no payment, and leaves no proof file on disk.
- **AC-21-02**: The same bill with a pending transfer of Rp 200.000: an upload of Rp 150.000 succeeds; an upload of Rp 150.001 returns HTTP 422 and names Rp 150.000 as claimable.
- **AC-21-03**: Pending transfers by another guardian of the same student count toward `claimable`.
- **AC-21-04**: After the first transfer is rejected or cancelled, the claimable amount returns and a new upload for the full balance succeeds.
- **AC-21-05**: A linked guardian cancelling a pending transfer sets `status = rejected`, `reject_reason = 'Dibatalkan oleh wali'`, recomputes the bills, writes one audit row with action `cancelled_by_guardian` and the acting user, and creates no `payment_rejected` notification and no journal entry.
- **AC-21-06**: Guardian B, linked to the same single child, can cancel a pending transfer submitted by guardian A.
- **AC-21-07**: A guardian not linked to every student allocated by the payment receives HTTP 403, whatever the payment's status. An admin, student, teacher, principal or finance user receives HTTP 403. A cash payment receives HTTP 403.
- **AC-21-08**: A linked guardian cancelling a `verified`, `rejected` or `void` transfer receives HTTP 422 and nothing changes.
- **AC-21-09**: With two pending transfers claiming disjoint amounts of one bill, verifying both in either order succeeds and the bill ends `paid`.
- **AC-21-10**: Verifying pending transfer P with a re-allocation onto a bill whose claimable amount (excluding P) is smaller than the new allocation returns HTTP 422; P stays `pending`.
- **AC-21-11**: Verifying without re-allocation and cash payments behave exactly as before this spec.
- **AC-21-12**: Two uploads of Rp 350.000 for the same Rp 350.000 bill submitted concurrently: exactly one succeeds, the other returns HTTP 422 (pgsql).
- **AC-21-13**: A cancel racing a verify on the same payment leaves exactly one outcome: either `verified` with a receipt number, or guardian-cancelled with none. Never both, and no journal entry for a cancelled payment (pgsql).
- **AC-21-14**: On the portal, the cancel action shows only when `can_cancel` is true, and the upload modal disables bills whose claimable amount is 0.
- **AC-21-15**: The trial balance still balances and the receivables reconciliation difference is 0 after any sequence of uploads, cancels, verifications and rejections.

## Constraints & Assumptions

- No schema change: `reject_reason` text distinguishes a guardian cancel from a school rejection in the portal; the audit action and actor distinguish it for reporting. If reporting later needs a first-class flag, add a column then.
- The test suite runs on sqlite, which has no `FOR UPDATE`. AC-21-12 and AC-21-13 must be proven on pgsql (`sail exec -e DB_CONNECTION=pgsql -e DB_DATABASE=testing -e DB_HOST=pgsql laravel.test php vendor/bin/phpunit …`).
- Sits under spec 17: no new notification type.

## Open Questions

- `[NEEDS DECISION: Cashier vs pending]` — should a cash payment on a bill be blocked or warned when a pending transfer already claims it? Without it the cash payment can make a later verification fail. Proposed: show the "menunggu verifikasi" flag in the cashier and allow, since the treasurer can reject the transfer. Treasurer to confirm.

## Out of scope, noted

- The portal summary "belum dibayar" uses `remainingAmount()`, which still includes amounts claimed by pending transfers, so it overlaps "menunggu verifikasi". Pre-existing, not changed here.
