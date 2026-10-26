# 19 — In-App QR Scanner (Pemindai Kamera Web)

Status: v1.1 — code implemented on `in-app-qr-scanner` (2026-10-27); automated and Chromium synthetic-camera checks passed; physical-camera performance and required desktop-browser acceptance pending. Audit revision of v1.0 (2026-10-05), sharpened 2026-10-26; depends on 03/15/16; amends 08/15.

> **Motivation:** A staffed gate can use a laptop webcam to read student and employee Dynamic QR codes, record attendance through the existing scan engine, and show the result without dedicated scanner hardware.

## Problem

The hardware scan endpoint requires a shared `X-Scanner-Key`. Schools need a browser camera workflow without exposing that device secret to JavaScript. Dedicated gate accounts also need server-enforced isolation from school administration, teaching, and employee self-service surfaces.

## Goals

1. Provide `/scanner` for camera-based Dynamic QR attendance recording.
2. Add an exclusive `scanner_operator` role (Petugas Piket / Operator Scanner), with scan feedback and a temporary log but no master-data browsing, reports, or attendance editing.
3. Reuse student and employee scan engines, preserving tap resolution, server debounce, and append-only audit events. Web scans always use server time.
4. Show names and authoritative attendance status, with visual feedback for every result and optional audio.

## Non-goals

- Replacing hardware scanners or reading RFID in the browser.
- Offline buffering, automatic request replay, or a new idempotency protocol.
- Master-data, attendance-editing, or reporting permissions for scanner operators.
- Multi-camera selection in v1; use the browser's default camera.
- Per-class scanner restrictions or scan-source analytics in v1.

## User Stories

- **As a gate operator**, I want to start my laptop camera and scan student or employee QR codes so I can record arrivals and departures.
- **As an administrator**, I want to provision a dedicated gate login that cannot access other school data or settings.
- **As a teacher on gate duty**, I want a clickable Scanner destination and clear attendance feedback for any student or employee.
- **As an operator**, I want camera, session, and network failures to explain the next action without claiming an unconfirmed attendance result.
- **As an administrator**, I want web and hardware attempts to use the same attendance rules and audit outcomes.

## Decisions

### Roles, authorization, and entry paths (amends 08/15)

- Add `UserRole::ScannerOperator = 'scanner_operator'`. Like `student`, this role cannot be combined with any other role. Enforce this on user creation and role updates, preserving the last-admin guard.
- Active `scanner_operator` users, admins, and teachers may access both scanner routes. Teacher scanning is school-wide for **students and employees** because this is a gate function; it grants no additional editing or master-data permissions. Authorization follows the union of held roles, as in spec 15.
- Admin creates/edits the operator account through existing user management. Use existing password and session configuration; this feature does not extend session lifetime.
- Login for an operator lands on `/scanner`; `GET /dashboard` redirects there without building dashboard data.
- Every eligible persona has a clickable Scanner sidebar entry. For admin/teacher, the path is login or role switch → dashboard/sidebar → Scanner. For operators, login → Scanner, with Scanner as the only product destination and logout in the user menu.
- Operator support exceptions are `POST /logout`, the dashboard redirect, and the configured login/challenge endpoints needed to complete authentication. Implementation must enumerate those existing authentication routes explicitly; do not exempt all authenticated routes or all settings routes. Public pages and required static assets are outside the product restriction.
- All other protected product pages **and mutation endpoints** deny operator access server-side with HTTP 403, including settings, notifications, announcements, and employee self-service even if a profile is linked. Hiding navigation alone is insufficient.
- The operator shell/shared props load only data needed for scanner operation and authentication. They must not load dashboard, notification, announcement, or other unrelated product payloads. Its user menu exposes logout without links to settings.
- Recheck active-account status and authorization on each scan request. Role removal/account deactivation takes effect on the next request; scanner sessions retain no cached permission bypass.

### Web scan contract and shared dispatch

The browser submits only to authenticated, CSRF-protected `POST /scanner/scan`. It never receives a scanner key or calls the hardware API.

- Request: `{ credential: string }`, required, non-empty, maximum 255 characters. Accept Dynamic QR tokens only. No browser timestamp or caller-selected scan method influences attendance processing.
- Use `ScanMethod::DynamicQr` and the server clock in the configured school timezone. Student and employee lateness thresholds remain owned by their existing engines.
- Match existing hardware dispatch: resolve the credential; a resolved employee uses `EmployeeScanService::scanResolved(...)`; all other cases, including credential resolution failures, go through `AttendanceScanService::scan(...)`, the existing audited student entry point. There is no student `scanResolved()` method today.
- Never return a resolver error before its event is recorded. A shared dispatch extraction is acceptable if it preserves hardware behavior and exactly one audit event per processed attempt.
- Apply a web-specific limit of 60 attempts per minute per authenticated user. Return HTTP 429 with a retry delay when exceeded; this does not change the hardware IP-based limiter.
- Each authenticated, authorized, validation-passing attempt completed by the engine produces exactly one `scan_events` row with `scan_method = 'dynamic_qr'`, including ignored and credential-error results. Authentication, CSRF, authorization, validation, and throttle rejections produce no scan event or attendance mutation. A server/transport failure does not prove whether an attempt committed.
- Web and hardware events share outcome codes; v1 adds no source column. The hardware response contract remains compatible; the web response adds attendance status for display.

**HTTP 200 — resolved, non-error result:**

```json
{
  "outcome": "checked_in",
  "detail": "check_in",
  "student_name": "Example Name",
  "subject_type": "student",
  "attendance_status": "present"
}
```

- `outcome`: coarse bucket `checked_in`, `checked_out`, or `ignored`.
- `detail`: exact `ScanOutcome` value, used for feedback and stored in the event.
- `student_name`: resolved subject name, including employees (legacy field naming).
- `subject_type`: `student` or `employee`.
- `attendance_status`: resulting student/employee attendance record's status, or `null` when no record exists. Derive it from the service result; never infer lateness from the browser clock or `absent_upgraded`.

**HTTP 422 — credential engine error:** `{ "outcome": "error", "detail": "error_expired_token" | "error_unknown_credential" }`. Unresolved credentials expose no name or subject type. Validation errors also use HTTP 422 but follow the application's validation envelope; they are not credential outcomes and create no scan event. The client must handle these separately.

### Submission state and recovery

- The page starts idle. A **Start Scanner** action requests the default camera and initializes audio through user interaction. Render a live viewfinder when ready; a **Stop** action releases camera tracks and stops decoding.
- Allow at most one request in flight. Show processing state and suppress further submissions until it settles. Never queue decoded credentials for later submission.
- Use a **global two-second minimum interval between request starts**, measured with elapsed time. Discard decodes during cooldown; do not queue them.
- After any submitted scan, require a new presentation: continue observing frames but do not rearm until **no QR has been detected for one continuous second**, the request has settled, and cooldown has elapsed. A changed/rotated token while a QR remains visible does not rearm scanning. Prompt the operator to remove the code before the next person presents theirs.
- Backend `scan_debounce_minutes` remains authoritative across presentations, sessions, tabs, and hardware devices. Client guards do not replace server rules.
- Never automatically retry a submitted request after timeout, lost response, or server failure. The request may already have committed. Pause submissions and show **“Hasil belum terkonfirmasi” / “Result not confirmed”**, explaining that attendance may have been recorded. Offer an explicit **Resume scanning** action after the operator follows the school's verification process; this action does not replay the credential and still requires code removal before a new presentation.
- Session expiry or CSRF failure halts scanning and offers reauthentication; a role/active-account rejection halts scanning and explains loss of access. After reauthentication, require Start Scanner and a fresh presentation.
- On HTTP 429, pause until the server retry delay has elapsed and show the remaining wait. Resume only through an operator action and a fresh presentation; never replay the rejected credential.

### Result display and temporary log

Show the latest result card for four seconds, replacing it immediately when a newer response arrives. Result-card duration does not block the next eligible scan. Status text/icons must communicate meaning independently of color or audio.

| `outcome` | `detail` / attendance status | Color | Display text | Sound |
|---|---|---|---|---|
| `checked_in` | `check_in` or `absent_upgraded`, `present` | Green | {name} — Hadir | Success beep |
| `checked_in` | `check_in` or `absent_upgraded`, `late` | Yellow | {name} — Terlambat | Warning beep |
| `checked_out` | `check_out` | Blue | {name} — Pulang | Success beep |
| `ignored` | `ignored_debounce` | Gray | {name} — Sudah Tercatat | None |
| `ignored` | `ignored_complete` | Gray | {name} — Sudah Selesai | None |
| `ignored` | `ignored_excused` | Gray | {name} — Izin/Sakit | None |
| `ignored` | `ignored_alumni` | Gray | {name} — Tidak Terdaftar Aktif | None |
| `ignored` | `ignored_inactive` | Gray | {name} — Pegawai Nonaktif | None |
| `error` | `error_expired_token` | Red | Kode Kedaluwarsa | Error buzz |
| `error` | `error_unknown_credential` | Red | Tidak Dikenali | Error buzz |

- The same absent-upgrade outcome may produce present or late status; there is no unconditional “late upgrade” label.
- Show the most recent 50 results, newest first, in page memory only: receipt time, returned name when available, subject type when available, and result. Clear on leaving, reload, logout, or loss of access. Do not persist in browser storage, fetch historical events, or retain raw QR tokens in the log, diagnostics, or analytics.
- Unconfirmed transport/server failures show a separate warning and never a success result or invented scan outcome.
- Fullscreen and audio are optional enhancements. If unavailable/blocked, scanning and visual feedback remain usable.

### Camera and browser failure states

- Distinguish idle, starting, ready, processing, waiting for code removal, paused, and error states.
- Missing camera API/insecure context: explain HTTPS or supported-browser requirements without repeatedly prompting.
- Permission denied: give browser-settings instructions and an explicit retry action. A permission prompt left unanswered stays in starting state with a cancel action; it must not claim permission was denied.
- No camera, camera busy/unavailable, or a track ending: show the reason when known, release resources, and offer retry after correction.
- Stop decoding and release all camera tracks on Stop, navigation/unmount, logout, or loss of access. If asynchronous startup completes after cancellation, release that stream too.
- On page hide, pause scanning and release the camera; returning requires explicit Start Scanner. Handle network disconnection without buffering scans or claiming attendance was recorded.

## Requirements and dependencies

All decisions above are required for v1; audio/fullscreen degrade gracefully.

1. **Scanner UI:** authenticated Inertia page `resources/js/pages/scanner/index.tsx`, with start/stop controls, viewfinder, submission states, feedback, bounded log, and entry paths for each authorized persona. Use existing frontend HTTP/navigation conventions and generated route functions.
2. **Web endpoint:** session auth, CSRF, role/active-account checks, validation, user-based throttle, shared engine dispatch, server clock, and the response contract above.
3. **Operator integration:** enum, exclusive-role create/update validation, provisioning UI, login/dashboard landing, minimal shell, and server-enforced product isolation. Existing roles retain their existing permissions.
4. **QR decoding:** use the approved, exactly pinned `html5-qrcode` 2.3.8 (Apache-2.0) behind the local camera adapter; real-device coverage against the supported-browser matrix is still required. Any dependency addition requires the repository's normal dependency approval. Do not assume a package's maintenance, license, API, or bundle size without verification. Decode at a bounded configurable rate, with only one camera/decoder instance; clean up streams, decoder work, timers, and pending callbacks on exit.
5. **Shared-engine prerequisite:** spec 03 requires `ignored_alumni` for students without an open enrollment in the active academic year whose start date is on/before the effective scan date. This is now implemented in the shared student engine and enum/coarse mapping, with hardware/web parity coverage. Preserve it as a scanner delivery prerequisite. Do not add a web-controller-only eligibility rule. Enrollment remains the only class-membership source.
6. **Dependencies:** spec 03 student engine, spec 15 role/user management, and spec 16 employee QR/attendance support. Employee scanning is required in v1, so spec 19 cannot ship independently of 16.

## Schema

No new tables or scan-source column. Extend the PHP role enum and role validation; the existing `user_roles.role` string storage accommodates `scanner_operator`. Extend shared scan outcomes with `ignored_alumni` as required by spec 03. Verify database constraints during implementation before concluding no schema migration is needed.

## Acceptance Criteria

- **AC-19-01:** An active operator logs in → lands on `/scanner` → chooses Start Scanner → grants permission → sees a camera feed. Sidebar contains only Scanner; user menu provides logout. `/dashboard` redirects without loading dashboard data.
- **AC-19-02:** Given a ready scanner and a current student QR for an eligible student with no attendance today, before the configured lateness threshold, a fresh presentation records check-in and displays the returned name with Hadir/green. The two-second camera-to-feedback target is measured only under the reference fixture below.
- **AC-19-03:** A correctly signed QR older than the server's 30-second validity window returns HTTP 422, displays Kode Kedaluwarsa, and records exactly one `error_expired_token` event. An invalid/unknown token similarly records `error_unknown_credential` without exposing identity.
- **AC-19-04:** Repeated decode callbacks produce one request; a slow response prevents concurrent submissions even after two seconds. A continuously visible QR, including token rotation and visibility beyond server debounce, never submits again. Removing all QR codes for one second and presenting another after cooldown permits the next scan without waiting for the four-second card to disappear.
- **AC-19-05:** After proper removal and re-presentation within server debounce, the same student shows Sudah Tercatat and creates one `ignored_debounce` event without mutation.
- **AC-19-06:** Operator direct GET and mutation requests to unrelated protected product endpoints (including attendance, students, settings, notifications, announcements, and employee self-service) return 403 without product data or mutations. The explicit auth-support exceptions, dashboard redirect, logout, public pages, and assets remain usable.
- **AC-19-07:** Admin and teacher each complete login/role switch → sidebar → Scanner → scan. Both can scan students outside any assigned class and employees; unrelated permissions are unchanged. Users holding no eligible role cannot GET the page or POST a scan.
- **AC-19-08:** Each completed engine attempt has exactly one dynamic-QR audit event, including errors/ignored results, and matches hardware resolution for equivalent subject, time, and initial attendance state. Auth/CSRF/authorization/validation/throttle rejection creates no scan event or attendance mutation.
- **AC-19-09:** Permission denial, unanswered prompt/cancel, no camera, busy camera, unsupported API, insecure context, and track termination show actionable states without a crash or automatic retry loop. Stop, navigation, hidden page, and cancelled startup release camera resources; returning requires explicit start. Blocked audio/fullscreen do not block scanning.
- **AC-19-10:** Active employee QR codes dispatch through the employee engine, display the employee name and returned present/late status, and record events linked to the employee. Inactive employees show Pegawai Nonaktif with `ignored_inactive` and no attendance mutation.
- **AC-19-11:** Late check-in, checkout, excused, completed, and absent-upgrade feedback matches returned `detail` and `attendance_status`. An absent record upgraded before the lateness threshold shows Hadir; after it shows Terlambat. Browser clock manipulation/caller timestamps cannot change web scan time or token validity.
- **AC-19-12:** A student without the enrollment required by spec 03 yields `ignored_alumni` and no attendance mutation on both hardware and web paths. Eligibility is owned by the shared engine.
- **AC-19-13:** Admin can create/update an operator account. Combining `scanner_operator` with any other role is rejected on both create and update; the last-admin guard remains effective.
- **AC-19-14:** Session expiry, CSRF rejection, account deactivation, and role revocation halt further submissions. Reauthentication/restart requires explicit action. Operator logout works and clears camera/log state; unrelated shared product props are absent.
- **AC-19-15:** A request committed by the server whose response is lost shows Result not confirmed, pauses, and produces no automatic replay. Resume scanning does not resubmit the old credential. Offline/server failures likewise avoid success claims; HTTP 429 honors the retry delay and requires explicit resume.
- **AC-19-16:** Empty/oversized credentials show validation feedback without a credential-result card or scan event. The temporary log retains at most 50 results and clears on exit/reload/logout/loss of access, with no raw tokens or browser-storage persistence.

## Verification and performance fixture

- Use focused backend integration tests for route authorization, dispatch/audit parity, response status, role exclusivity, rejection behavior, and server-clock authority. Keep established tap-resolution cases in their service tests; add the missing alumni prerequisite there rather than duplicating the entire engine suite.
- Exercise submission/rearming and lost-response cases with controlled responses; manually verify real camera permissions, cleanup, and door-to-door navigation for all three personas.
- Required desktop matrix: current stable Chrome and Edge on Windows, and Safari and Chrome on macOS, using HTTPS and a built-in/default camera. Record exact browser/OS versions and device/camera used in the implementation verification. Mobile scanning is outside the v1 acceptance matrix.
- Reference performance fixture: supported desktop laptop, unobstructed camera in normal indoor light, in-focus phone QR, scanner already ready, warm application, network round-trip at most 100 ms, and no injected failures. Record the device/browser fixture and camera-to-card latency for ten fresh valid check-in presentations; target at most two seconds per presentation. This is a controlled verification target, not a universal webcam/network guarantee.

## Deferred decisions

- Source differentiation (`hardware`/`web`) is deferred; retain `scan_method = 'dynamic_qr'` for both in v1.
- Per-class gate restrictions, multi-camera selection, offline queues, and automatic retry/idempotency require a future spec amendment.
