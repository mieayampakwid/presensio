# 04 — Absence Excuses (Sakit & Izin)

Status: draft v2.0 (2026-09-16)

## Purpose

Guardians submit absence excuses (Sakit or Izin) on behalf of their children; an admin approves or rejects them; approval safely coordinates with the attendance engine.

## Decisions

- **Approvals**: Only **admins** approve/reject in v1 (teachers see excuses for their class read-only).
- **Data Structure**: An excuse covers one child (student) and one date **range** (single day = 1-day range). 
- **Categorization**: Excuses must be strictly categorized as either `sick` (Sakit) or `leave` (Izin), replacing the generic 'excused' status to align with Indonesian educational reporting standards.
- **Future Dates**: Guardians *can* submit excuses for future dates (Advance Leave). This elegantly pre-fills the system, preventing the Auto-Absent Cron job (Spec 03) from falsely marking them 'Absent' in the upcoming days.
- **Evidence / Attachments**: Guardians can attach proof (e.g., Doctor's note for `sick` or invitations/travel tickets for `leave`). The system will accept common image files (JPG/PNG) or PDFs.
- **Resolution Flow**:
  - On **approval**, every attendance record in the requested range becomes exactly `sick` or `leave`. If no record exists for a weekday within that future/past range, a new attendance record is automatically injected with that status.
  - On **rejection**, attendance records generally remain untouched (they either revert to absent or stay tracking via RFID). The guardian sees the rejection along with admin feedback.

## Requirements

1. Logged-in Guardian can submit an excuse for their linked child: 
   - Date range (start ≤ end)
   - Category (`sick` or `leave`)
   - Reason text (required)
   - Attachment (optional, file upload handling)
2. Guardian dashboard lists their submissions with status (`pending`, `approved`, `rejected`) and the admin's optional review note.
3. Admin dashboard shows pending excuses with: child (`full_name`), class, dates, category, reason, attachment link/thumbnail, and the current attendance state of those corresponding dates.
4. Approving applies the record changes atomically; re-approval of an already-approved excuse is a no-op.
5. Admin can append an optional `review_note` directly visible to the guardian on approve/reject.

## Schema Expected (excuses)

- `id` (primary)
- `student_id` (foreign)
- `type` (enum: `sick`, `leave`)
- `start_date`, `end_date` (date)
- `reason` (text)
- `attachment_path` (string, nullable)
- `status` (enum: `pending`, `approved`, `rejected`)
- `review_note` (string, nullable)
- `reviewed_by_user_id` (foreign -> admin users, nullable)

## Out of scope

- Teacher-side approval workflow
- Multi-child excuses in a single submission (Guardian must submit per child to bind attachment accurately)
- Built-in PDF generation rendering for formal leave letters