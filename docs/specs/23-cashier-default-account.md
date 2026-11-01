# 23 — Cashier Default Cash Account

Status: draft v1.0 (2026-11-01)

## Problem

The cashier screen (`/fees/cashier`) pre-selects the cash account for a new payment by hard-coding the account code `1-1100` (`CashierController::KAS_TUNAI_CODE`), falling back to the first active cash account by code. Cashiers can already choose any active cash account per payment — only the default pre-selection is hard-coded. A school whose chart of accounts numbers its cash accounts differently gets the wrong account pre-selected on every transaction, and a habitual Enter posts cash to the wrong ledger account. This was left as a known shortcut when Fees C shipped (recorded in the repo status block).

## Goals

1. Make the cashier's pre-selected cash account a school setting, editable by an admin, instead of a hard-coded account code.
2. Follow the existing default-account pattern exactly: `default_transfer_account_id` on the `settings` row, edited on the school profile settings page through the audited update path.
3. Remove the `KAS_TUNAI_CODE` constant from `CashierController`.

## Non-goals

- Per-user cashier defaults (all cashiers share the school default).
- Restricting which accounts a cashier may select per payment — the per-transaction picker and its validation (`is_cash`, `is_active`) stay exactly as they are.
- Making the default mandatory; `null` keeps a sensible fallback (see decisions).
- Touching the guardian-transfer default (`default_transfer_account_id`, spec 14/22) — that setting drives verification and stays separate.
- Changing posting behavior: a payment always posts to the submitted account, never silently to the default.

## Decisions

1. **Storage:** nullable `cashier_default_cash_account_id` on the `settings` row (`SchoolSetting`), following `default_transfer_account_id`. Consumed via `SchoolSettings::cashierDefaultCashAccountId(): ?int`.
2. **Editing:** the school profile settings page (`/settings/school`), admin-only via the existing `UpdateSchoolProfileRequest` path, with the same audit trail (old → new values in `audit_logs`). Listed alongside the existing default transfer account select, from active, postable cash accounts.
3. **Validation:** `nullable` + exists on `accounts` where `is_cash`, `is_active`, `is_postable` — the same predicate as `default_transfer_account_id`.
4. **Resolution order for the cashier pre-selection:** setting → first active cash account by code → `null` (no pre-selection). The `1-1100` constant and its code lookup are deleted. On the standard seeded CoA `1-1100` sorts first among cash accounts, so an unset setting preserves today's behavior for existing installations.
5. **Effect window:** the default affects only the pre-selection of future cashier sessions. Recorded payments, and their journal lines, never change when the setting changes.

## Acceptance Criteria

| ID | Criterion |
| --- | --- |
| 23-01 | An admin can set and clear the default cash account on the school profile page; the change is audit-logged with old and new values. |
| 23-02 | The setting accepts only accounts with `is_cash`, `is_active` and `is_postable`; any other account id fails validation. |
| 23-03 | With the setting configured, the cashier screen pre-selects that account. |
| 23-04 | With the setting null, the cashier screen pre-selects the first active cash account by code; `CashierController` contains no account-code constant. |
| 23-05 | A non-admin cannot update the setting (authorization unchanged from the school profile form). |
| 23-06 | Changing the setting does not alter existing payments or journal entries. |
| 23-07 | The per-payment account selection and `StoreCashPaymentRequest` validation behave exactly as before. |

## Constraints & Assumptions

- Single settings row per school (`SchoolSettings::row()`); no history beyond `audit_logs`.
- Pre-production migration posture: a plain additive nullable column migration is fine.
- The school profile page is not yet translated (i18n rollout slice 2 covers settings pages); new UI strings follow that page's current string convention and get lang keys in slice 2.

## Open Questions

- `StoreCashPaymentRequest` and the cashier's account list require `is_cash` + `is_active` but not `is_postable`, while the school-profile cash list and this new setting require all three. Should the cashier list/request also filter `is_postable` for consistency? (Small alignment; answer before or during implementation — engineering.)
