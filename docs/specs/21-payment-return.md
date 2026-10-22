# 21 — Payment Return Workflow (Pembatalan & Batas Klaim Transfer)

Status: Draft v1.2 (2026-10-22) — Ready for planning; not implemented. Follow-up to spec 14 (Fees C), deferred by the user on 2026-10-18 after the Fees C browser click-through. Amends spec 14 "Payment status" and the Guardian Portal upload rules; does not change the ledger. v1.2 resolves the review findings: all eligible pending transfers remain reachable, displayed claimable balances cannot be negative, cancellation follows spec 15's union-of-roles authorization, and claim-limit feedback respects payment visibility. Supersedes v1.1 (2026-10-18), which introduced explicit claim-check mode, re-allocation checks, linked-guardian cancellation and upload-race coverage.

## Problem

A guardian can upload several pending transfers for the same bill. The per-bill limit in spec 14 (`allocation ≤ net − paid`) counts only verified payments, so two pending transfers can each claim the full remaining balance. The treasurer verifies the first; verifying the second then fails the limit check and the only way out is to reject a transfer the guardian made in good faith. A guardian who uploaded the wrong proof or amount also has no way to withdraw it; only the school can reject it.

## Goals

- A guardian cannot create pending transfers that together claim more than a bill's remaining balance.
- A guardian linked to the students of a pending transfer can withdraw it without involving the school.
- A guardian upload or a verification re-allocation can never make another pending transfer fail verification.
- Every eligible pending transfer remains reachable for cancellation, regardless of its age or the number of newer payments.

## User Stories

- As a linked guardian, I want to see how much of a bill is still claimable so that I do not submit a transfer that duplicates a pending claim.
- As a linked guardian, I want to find and cancel an old pending transfer so that I can correct a mistake without asking the school to reject it.
- As a teacher or finance employee who is also a parent, I want the same cancellation rights for my children as other guardians.
- As a guardian whose bill is claimed by a transfer I cannot access, I want a clear next step without seeing another family's payment details.

## Requirement Priorities

- **P0:** Claim limits and transaction rules (AC-21-01…04, 09…13, 15); authorized cancellation and reachable pending transfers (AC-21-05…08, 17…18, 21); non-negative UI balances and privacy-safe feedback (AC-21-14, 16, 19…20). All are required for this release.
- **P1, deferred:** A cashier warning about pending claims, subject to treasurer confirmation. It does not block this release or change cash-payment rules.
- **P2, separate scope:** Receiving and refunding excess money (spec 22). This spec does not depend on it.

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
3. **Violation.** An upload or re-allocation that exceeds `claimable` on any bill is a `PaymentOperationException`. The upload identifies the affected bill and `max(0, claimable)` on the `allocations` field, and the controller deletes the stored proof (as it already does for validation failures). When pending claims cause the rejection, the message says: "Ada transfer menunggu verifikasi untuk tagihan ini. Periksa daftar transfer yang dapat Anda batalkan atau hubungi bendahara." It must not promise that the blocking transfer is visible or cancellable, or disclose its ID, uploader, other students or allocations. Insufficient balance without a pending claim uses the ordinary remaining-balance error. JSON requests return HTTP 422; browser/Inertia validation redirects back with field errors, following the existing upload flow. Other operation rejections follow the existing finance response convention (HTTP 422 for non-Inertia callers, error feedback for Inertia).
4. **Who may cancel.** The user must hold the `parent` role, have a guardian profile, be linked to every student whose bill the payment allocates, and the payment must be a `bank_transfer` with at least one allocation. This follows spec 15: held roles grant a union of capabilities; active role only affects UI. An `admin + parent`, `teacher + parent`, `finance + parent` or `principal + parent` has the same linked-guardian rights as a parent-only user. No other role grants cancellation by itself. Check role, profile, method and links first (HTTP 403, without revealing status), then status (HTTP 422 if not `pending`). Re-read the allocations and authorization after acquiring the payment lock and before checking status, so concurrent verification/re-allocation cannot bypass the access rule.
5. **Effect of cancel.** In one transaction: lock the payment, re-check `pending`, set `status = rejected` and `reject_reason = 'Dibatalkan oleh wali'`, recompute the affected bills, write an audit row with action `cancelled_by_guardian` and the acting user. No `payment_rejected` notification. The proof file is kept, as for any rejected payment. Cancel is its own service method and does not call `reject()`, because `reject()` notifies.
6. **Lock order.** Upload locks the allocated bills (`ORDER BY id … FOR UPDATE`) before summing pending allocations, inside the upload transaction; it holds no payment lock. Verify, reject and cancel lock the payment first, then bills ordered by id (the existing `PaymentService` order). Two uploads for one bill therefore serialize on the bill lock, and the second sees the first's committed allocations.
7. **Races.** Cancel, verify and reject all re-check `pending` under the payment lock. Cancellation performs rule 4's authorization before the status check; it must not expose a status error to an unauthorized user. An authorized operation that loses the race gets the "no longer pending" rejection and changes nothing.
8. **Existing overlaps.** Pending transfers that already over-claim a bill when this ships are not migrated. Verification without re-allocation keeps the existing remaining-balance check: it may succeed for the first transfer if the remaining amount covers it; later attempts that exceed the remaining amount fail. Re-allocation continues to use the claim check. The treasurer can reject, or an eligible guardian can cancel, the remaining claims. Raw `claimable` may be negative after these overlaps or the school actions excluded above; the server still rejects every positive allocation when it is non-positive. No negative balance is presented as money available to pay.

## Portal UI (`/my-fees`)

- Each bill carries a display `claimable = max(0, remaining − pending allocations)`. The upload modal shows this instead of the raw remaining balance. Both individual selection and select-all require an open bill with positive claimable; partial pending claims do not exclude a bill that still has a positive claimable amount. Initial selected amounts and amount-input limits use claimable. The "menunggu verifikasi" flag is present whenever pending allocations exist, independently of whether claimable is zero. The server always revalidates against current data.
- Add a **Transfer Menunggu Verifikasi** section, paginated at 20 payments per page, newest first by ID. It includes only visible pending bank transfers (same all-linked-students rule as payment history) and is independent of the existing latest-50 history limit. Every eligible pending transfer is reachable through its pagination. Pagination preserves the rest of the portal; a successfully cancelled row leaves this list, the page refreshes its totals, and an empty page falls back to the last available page. No pending transfers produces a clear empty state.
- Each payment in the pending list and history carries `can_cancel`, computed on the server from rule 4 and `pending`; no role or active-role shortcut may grant it. A "Batalkan" action with a confirmation dialog shows only when it is true. After cancel, any occurrence in the existing history shows as rejected with the reason "Dibatalkan oleh wali", and the bill's "menunggu verifikasi" flag clears if no other pending payment remains. Older cancelled payments need not be added to the latest-50 history; the successful cancellation is confirmed to the guardian.
- The student read-only page has no cancel (students never upload).

**Clickable entry paths:** Parent active role → Dashboard/sidebar → Biaya Sekolah (`/my-fees`) → Transfer Menunggu Verifikasi → page containing the transfer → Batalkan → confirmation. A user holding parent plus another role switches to the parent UI to reach this path; server authorization still uses all held roles. Finance/admin → sidebar → Verifikasi Transfer for school rejection, or Payments → rejected filter to inspect the outcome. Browser verification must walk these paths, including a transfer older than the latest 50 history rows. No new top-level destination is needed.

## Access

| Action | admin | finance | principal | teacher | parent | student |
|---|---|---|---|---|---|---|
| Cancel a pending transfer | – | – | – | – | linked to all its students | – |

The matrix describes the capability each role grants, not a deny rule for users with several roles. Admins can open the parent routes (`role:parent,admin`), but cancellation separately requires the parent role and all rule 4 checks; admin alone does not qualify. Admin and finance retain Reject in the verification queue. The queue lists pending payments only, so a cancelled transfer leaves the queue and shows in the payments list (`/fees/payments`, status filter "rejected") with the guardian-cancel reason.

## Acceptance Criteria

Examples use whole rupiah; existing decimal-string money precision is unchanged. References to HTTP 422 below describe JSON/non-Inertia operation responses; browser validation and Inertia feedback follow rule 3.

- **AC-21-01**: A bill with net Rp 350.000, paid Rp 0 and one pending transfer of Rp 350.000: a second upload allocating any amount to that bill returns HTTP 422, creates no payment, and leaves no proof file on disk.
- **AC-21-02**: The same bill with a pending transfer of Rp 200.000: an upload of Rp 150.000 succeeds; an upload of Rp 150.001 returns HTTP 422 and names Rp 150.000 as claimable.
- **AC-21-03**: Pending transfers by another guardian of the same student count toward `claimable`.
- **AC-21-04**: After the first transfer is rejected or cancelled, the claimable amount returns and a new upload for the full balance succeeds.
- **AC-21-05**: A linked guardian cancelling a pending transfer sets `status = rejected`, `reject_reason = 'Dibatalkan oleh wali'`, recomputes the bills, writes one audit row with action `cancelled_by_guardian` and the acting user, and creates no `payment_rejected` notification and no journal entry.
- **AC-21-06**: Guardian B, linked to the same single child, can cancel a pending transfer submitted by guardian A.
- **AC-21-07**: A guardian not linked to every student allocated by the payment receives HTTP 403, whatever the payment's status. A user without the parent role, a parent without a guardian profile, a cash payment, or a payment without allocations receives HTTP 403. Rejected requests change no payment, bill, journal or audit data.
- **AC-21-08**: A linked guardian cancelling a `verified`, `rejected` or `void` transfer receives HTTP 422 and nothing changes.
- **AC-21-09**: With two pending transfers claiming disjoint amounts of one bill, verifying both in either order succeeds and the bill ends `paid`.
- **AC-21-10**: Verifying pending transfer P with a re-allocation onto a bill whose claimable amount (excluding P) is smaller than the new allocation returns HTTP 422; P stays `pending`.
- **AC-21-11**: Verifying without re-allocation and cash payments behave exactly as before this spec.
- **AC-21-12**: Two uploads of Rp 350.000 for the same Rp 350.000 bill submitted concurrently: exactly one succeeds, the other returns HTTP 422 (pgsql).
- **AC-21-13**: A cancel racing a verify on the same payment leaves exactly one outcome: either `verified` with a receipt number, or guardian-cancelled with none. Never both, and no journal entry for a cancelled payment (pgsql).
- **AC-21-14**: On the portal, the cancel action shows only when `can_cancel` is true, and the upload modal disables bills whose claimable amount is 0.
- **AC-21-15**: The trial balance still balances and the receivables reconciliation difference is 0 after any sequence of uploads, cancels, verifications and rejections.
- **AC-21-16**: Given a bill with remaining Rp 350.000 and legacy pending claims totalling Rp 700.000, the portal displays claimable Rp 0, disables individual/select-all selection for it, and rejects any positive upload allocation without storing a payment or proof. After enough claims are cancelled/rejected, the displayed balance and selection recover. The same non-negative display applies when a cash payment makes an existing claim exceed the remaining balance.
- **AC-21-17**: Given an eligible pending transfer followed by at least 50 newer visible payments, the guardian reaches it through Transfer Menunggu Verifikasi pagination and cancels it. It leaves the pending list, its claim is released, and the guardian gets success feedback. Cancelling the last row of a page leaves a valid page or empty state. No unrelated guardian's transfers appear on any page.
- **AC-21-18**: A linked user holding `parent` plus `admin`, `teacher`, `finance` or `principal` can cancel an eligible pending transfer, regardless of active role. Switching active role does not change the server result; switching to parent exposes the documented UI entry path. Users lacking parent remain forbidden as in AC-21-07.
- **AC-21-19**: Given remaining Rp 350.000 and a pending claim of Rp 200.000, both individual selection and select-all can select the bill with initial amount and limit Rp 150.000. The pending badge stays visible. A stale form that exceeds the current claimable amount receives field feedback, preserves recoverable input, and stores no payment or proof.
- **AC-21-20**: Given guardian A linked only to child X, and guardian B's pending transfer allocating to X and Y, A's conflicting upload names only A's affected bill and its non-negative claimable amount. The message offers checking cancellable transfers or contacting the treasurer; it exposes no details of B, Y or the inaccessible payment. B's transfer is absent from A's pending/history lists and A's direct cancellation request returns HTTP 403.
- **AC-21-21**: If verification changes a transfer's allocations before a waiting cancellation acquires the payment lock, cancellation checks the latest student links before status. A guardian no longer linked to every allocated student receives HTTP 403, with no cancellation audit or state change; an eligible guardian receives the terminal-status rejection from AC-21-13 (pgsql).

## Success Metrics

- **Release gate:** 100% of the acceptance criteria pass, including PostgreSQL race cases and the documented browser journeys. Every eligible pending transfer in the browser fixture is reachable, including those outside the latest 50 payments.
- **First-month review:** Target zero confirmed new over-claims caused by guardian upload or verification re-allocation, and zero unauthorized cancellations. Review reported incidents against payment allocations and cancellation audit rows at 30 days. Legacy overlaps and the explicitly excluded school actions are classified separately. These are correctness targets, not measured improvements over an established baseline; no new analytics system is required.

## Constraints & Assumptions

- No schema change: `reject_reason` text distinguishes a guardian cancel from a school rejection in the portal; the audit action and actor distinguish it for reporting. If reporting later needs a first-class flag, add a column then.
- The test suite runs on sqlite, which has no `FOR UPDATE`. AC-21-12, AC-21-13 and AC-21-21 must be proven on pgsql (`sail exec -e DB_CONNECTION=pgsql -e DB_DATABASE=testing -e DB_HOST=pgsql laravel.test php vendor/bin/phpunit …`).
- Sits under spec 17: no new notification type.

## Open Questions

- `[NON-BLOCKING — Treasurer: Cashier vs pending]` — should a future cashier change warn or block when pending transfers already claim the bill? This release preserves cash-payment behavior. Proposed follow-up: show the "menunggu verifikasi" flag and allow; treasurer to confirm separately.

## Timeline & Dependencies

- No hard deadline has been specified. Next step is an implementation plan for this scope, followed by focused tests, PostgreSQL concurrency verification, project checks and browser click-through before shipping.
- Depends on implemented specs 14 (payment services/portal), 15 (roles/audit) and 17 (existing notification behavior). It can ship before spec 22 and has no dependency on refund-fee decisions.
- No blocking product question remains for this scope; the cashier follow-up above stays deferred.

## Out of scope, noted

- The portal summary "belum dibayar" uses `remainingAmount()`, which still includes amounts claimed by pending transfers, so it overlaps "menunggu verifikasi". Pre-existing, not changed here.
