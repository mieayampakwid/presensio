# 24 — Individual Student Withdrawal (Pelepasan Siswa Individual)

Status: implemented 2026-11-09 (v1.0) — PRD / feature specification; amends 02, 07, 14, 15

> **Motivation:** Students occasionally leave mid-year (transferring to another school, family relocation, or dropping out). Currently, closing an enrollment without a successor only happens en masse during the academic year roll-over (spec 07). Admins have no UI path or dedicated endpoint to release a single student, leaving departed students on active attendance rosters, triggering erroneous morning absence notifications, and generating unwanted monthly fee bills.

---

## 1. Problem Statement

When a student departs mid-academic year, school administrators have no mechanism in the application to record their departure or close their active enrollment. Leaving the class dropdown empty on the student form intentionally preserves existing enrollment to avoid accidental un-enrollment (`StudentController::assignClass`), meaning administrators currently have no alternative other than direct database manipulation. Consequently, departed students continue to appear on daily teacher attendance rosters, trigger false-positive WhatsApp absence alerts to parents during the automated morning sweep, and accumulate automated fee receivables on the general ledger.

---

## 2. Goals

1. **Clean Enrollment Closure:** Provide an audited, admin-only UI action to terminate a student's active enrollment with an authoritative departure date (`ended_on`).
2. **Immediate Attendance Isolation:** Instantly exclude withdrawn students from all operational attendance surfaces (RFID/web QR scans yield `ignored_alumni`, morning sweeps skip them, and they disappear from active teacher rosters).
3. **Automated Fee Settlement:** Automatically cancel uncollected future fee bills (`status = cancelled`, `cancel_reason = 'Siswa keluar/lulus'`) and reverse receivable recognition journals via `BillService::cancelForWithdrawal`, while flagging partially paid bills for treasurer review.
4. **Historical Attribution Integrity:** Preserve past attendance, grades, and report card archives under `classOn(date)` without modifying previous historical records.
5. **Operational Audit Trail:** Record complete metadata (date, reason, destination school, notes, and actor) in `audit_logs` for regulatory and administrative compliance.

---

## 3. Non-Goals

1. **Mid-Year Class Reassignment:** Transferring a student between classes within the same school remains handled by selecting a new class in the edit student form (spec 02 v2.0).
2. **Automated Cash/Bank Refund Processing:** Any financial refund of excess tuition or deposit is handled through existing overpayment and manual journal workflows (spec 22 / spec 14), not automatically disbursed by the withdrawal action.
3. **Formal Government Dapodik Sync:** Bi-directional sync of mutation documents with Ministry systems is out of scope for v1 (deferred to v2.x).
4. **Permanent Record Deletion:** Withdrawing a student never deletes student master profiles, historical enrollments, attendance entries, or invoice history.
5. **Bulk Multi-Class Withdrawal:** Batch processing remains exclusively the domain of annual academic roll-overs (spec 07); mid-year withdrawal is executed student-by-student.

---

## 4. User Stories

### Administrator
- **As an admin**, I want to click "Keluarkan / Lepaskan Siswa" from the student listing or edit page, so that I can terminate their enrollment when they leave the school.
- **As an admin**, I want to see a clear preview of affected future bills and partially paid bills before confirming the withdrawal, so that I understand the financial impact.
- **As an admin**, I want to record the departure reason (e.g. pindah sekolah, mengundurkan diri) and optional notes (sekolah tujuan, nomor surat mutasi), so that administrative records remain audit-compliant.
- **As an admin**, I want the option to unassign the student's physical RFID card upon withdrawal, so that the card can immediately be issued to another student.

### Homeroom & Subject Teacher
- **As a homeroom teacher**, I want withdrawn students to disappear immediately from my daily attendance board and grading sheets, so that class averages and attendance percentages reflect only attending students.

### Guardian & Student
- **As a guardian of a withdrawn student**, I want to stop receiving daily attendance alerts or new tuition bill notices, while still being able to log into the portal to download past report cards and payment receipts.

### Treasurer / Finance Officer
- **As a school treasurer**, I want uncollected future tuition bills to be cancelled automatically and receivables reversed in the ledger, with any partially paid bills reported for my manual settlement review.

---

## 5. Requirements

### Must-Have (P0)

1. **UI Entry Point & Modal (Admin Only):**
   - Action item "Lepaskan Siswa" available in `/students` table row actions and on the `/students/{id}/edit` header.
   - Accessible only by users holding the `admin` role.
   - Opens a confirmation dialog displaying:
     - Student name, student number (NIS/NISN), and current active class.
     - **Departure Date (`ended_on`):** Date picker defaulting to school today. Must be $\ge$ current enrollment's `started_on` and $\le$ today.
     - **Withdrawal Reason (`reason`):** Required select:
       - `transfer_out` ("Pindah Sekolah / Mutasi Keluar")
       - `dropped_out` ("Mengundurkan Diri")
       - `expelled` ("Dikeluarkan")
       - `deceased` ("Meninggal Dunia")
       - `other` ("Lainnya")
     - **Notes / Destination School (`notes`):** Optional textarea (max 500 characters) for destination school name or mutation letter reference.
     - **Financial Impact Summary:** Real-time count of future zero-payment bills to be cancelled and count of partially paid bills requiring review.

2. **Transactional Backend Service (`EnrollmentService::release`):**
   - Executes inside a database transaction:
     - Sets `ended_on = $endedOn` on the student's currently open enrollment (`whereNull('ended_on')`).
     - Calls `BillService::cancelForWithdrawal($student, $endedOn)`.
     - Future unpaid bills are marked `cancelled` with reason `'Siswa keluar/lulus'`, and recognition journals are reversed.
     - Future partially paid bills are returned in the review list without cancellation.
   - Dispatches audit log entry:
     - Action: `student.withdrawn`
     - Target: `Student` model
     - Payload: `{ class_id, class_name, ended_on, reason, notes, cancelled_bills_count, review_bills_count }`

3. **Attendance & Scanner Behavior:**
   - Active scans at the gate (RFID or web QR) for the withdrawn student immediately evaluate as `ScanOutcome::IgnoredAlumni` on or after `ended_on`.
   - The automated morning sweep (`MarkAbsencesCommand`) skips the student starting on `ended_on`.
   - The student is excluded from live boards (`/reports/presence-board`) and active class attendance rosters.

4. **Student Master Listing & Status Filter:**
   - The `/students` index table displays an unambiguous status badge:
     - **Aktif:** Has an open enrollment (`ended_on IS NULL`) in the active academic year.
     - **Keluar / Alumni:** No open enrollment in the active academic year.
   - Filter dropdown on `/students`: `Semua Status`, `Aktif` (default), `Keluar / Alumni`.

### Nice-to-Have (P1)

1. **RFID Card Release Option:**
   - Checkbox in the withdrawal modal: *"Lepas kaitan kartu RFID"* (default checked if student has an assigned RFID card).
   - If checked, clears `rfid_cards.student_id = null`, freeing the card for new students while preserving `scan_events` history.
2. **User Account Management Option:**
   - Checkbox: *"Nonaktifkan akun portal siswa"* (default checked if student has a linked `user_id`).
   - If checked, sets `users.is_active = false`.
   - Guardian safety guard: Never deactivates the guardian account if the guardian has other actively enrolled children.
3. **Treasurer Review Notification:**
   - If `review_bills_count > 0`, dispatches an in-app notification to the `finance` / `admin` role notifying them of partially paid bills requiring settlement.

### Future Considerations (P2)

1. **Reinstatement / Undo Withdrawal:**
   - Dedicated flow to reinstate an erroneously withdrawn student within a grace period (re-opening enrollment and restoring cancelled bills).
2. **Official Mutation Letter (Surat Mutasi Keluar):**
   - PDF export generating the official school transfer letter with school letterhead (*kop surat*), student biographical data, destination school, and principal signature block.

---

## 6. Acceptance Criteria

| ID | Given | When | Then |
|---|---|---|---|
| **AC-24-01** | An admin views `/students` or `/students/{id}/edit` | Inspecting actions for an enrolled student | A "Lepaskan Siswa" button is visible and clickable; non-admins receive HTTP 403 on the endpoint. |
| **AC-24-02** | An admin opens the withdrawal modal | Providing a departure date prior to `started_on` | The form shows a validation error and prevents submission. |
| **AC-24-03** | An enrolled student has 3 future unpaid bills and 1 future partially paid bill | Admin confirms withdrawal with today's date | The open enrollment's `ended_on` is stamped with today's date; the 3 unpaid bills are cancelled with recognition journals reversed; the 1 partially paid bill remains open; response summarizes bills cancelled. |
| **AC-24-04** | A student has been withdrawn today | The student taps their RFID card or presents Dynamic QR at the gate today | The scan creates a `scan_events` row with `outcome = 'ignored_alumni'`, creates no attendance record, and displays alumni feedback. |
| **AC-24-05** | A student has been withdrawn today | The daily morning sweep (`attendance:mark-absent`) executes | The withdrawn student is skipped and receives no absent record or WhatsApp absence alert. |
| **AC-24-06** | A student is withdrawn | Viewing historical attendance and report cards from last month | Historical views attribute attendance and grades to the historical class without error (`classOn(date)` remains intact). |
| **AC-24-07** | A student withdrawal is executed with "Lepas kartu RFID" checked | The withdrawal transaction completes | The assigned `rfid_cards` row has `student_id = null`, and the card becomes available for assignment to another student. |
| **AC-24-08** | A student with a linked user account is withdrawn with "Nonaktifkan akun portal" checked | The student attempts to log in | Login is rejected with the standard account deactivated message (`is_active = false`). |
| **AC-24-09** | A guardian is linked to Student A (withdrawn) and Student B (actively enrolled) | Student A is withdrawn with user deactivation checked | The guardian's user account remains active (`is_active = true`) because Student B remains enrolled. |
| **AC-24-10** | A withdrawal is completed | Admin visits `/admin/audit-logs` | An audit log entry `student.withdrawn` is recorded with actor, timestamp, student ID, class ID, reason, and notes. |

---

## 7. Success Metrics

### Leading Indicators
- **0 Direct DB Interventions:** School administrators execute 100% of student withdrawals through the UI without developer intervention.
- **0 False-Positive Sweeps:** Zero morning absence notifications generated for departed students after the withdrawal date.
- **Task Completion Time:** Completing a student departure takes under 30 seconds for an administrator.

### Lagging Indicators
- **Receivables Accuracy:** 100% elimination of phantom receivables on the general ledger resulting from departed students.
- **Roster Hygiene:** Active class rosters reflect 100% physically attending headcount at any point in the academic year.

---

## 8. Decisions (Confirmed 2026-11-09)

1. **Schema & Storage (In-Place Migration):**
   Add `ended_reason` (`varchar(50)`, nullable) and `ended_notes` (`text`, nullable) directly to the existing migration `2026_09_21_000001_create_enrollments_table.php`. In-place edits are approved as the project is in pre-production. `ended_reason` is backed by `App\Enums\WithdrawalReason`. Roll-over closures (spec 07) also stamp `ended_reason = WithdrawalReason::Graduated`.

2. **Portal Access & Guardian Account Policy:**
   Guardian accounts remain active by default with read-only access to past reports and payment receipts. An explicit optional checkbox in the withdrawal modal allows an admin to immediately revoke portal access (`is_active = false`) when specifically requested.
---

## 9. Timeline & Dependencies

- **Dependencies:**
  - `EnrollmentService::release()` — already implemented.
  - `BillService::cancelForWithdrawal()` — already implemented.
  - `AuditLogger` — already implemented.
- **Estimated Scope:** 1 small feature plan (1 controller action, 1 FormRequest, 1 React modal dialog, update to `/students` index and edit pages, feature tests).
