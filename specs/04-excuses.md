# 04 — Absence Excuses

Status: draft v1 (2026-09-15)

## Purpose

Parents submit absence excuses; an admin approves or rejects them; approval updates attendance records.

## Decisions

- Only **admins** approve/reject in v1 (teachers see excuses for their class read-only).
- An excuse covers one child and one date **range** (single day = 1-day range), with a free-text reason. No attachments in v1.
- On approval, every attendance record in the range becomes `excused`, and excused records are **created** for weekdays in the range that have no record yet. Weekends are skipped; no holiday calendar in v1 (same limitation as spec 03).
- On rejection, attendance records are left untouched. The parent sees the rejection but not who rejected it.

## Requirements

1. Parent can submit an excuse for their child: date range (start ≤ end, not in the future beyond today) + reason (required).
2. Parent sees their submissions with status (`pending`, `approved`, `rejected`) and the admin's optional review note.
3. Admin sees pending excuses with child, class, dates, reason, and current attendance state of those dates.
4. Approving applies the record changes from Decisions atomically; re-approval of an already-approved excuse is a no-op.
5. Admin can add an optional review note on approve/reject.
6. Excuse submission does not modify attendance records while `pending`.

## Out of scope

- Teacher-side approval
- Attachments (doctor's notes)
- Multi-child excuses in one submission (submit per child)
- Notifications about excuse decisions (candidate for spec 05 extension)
