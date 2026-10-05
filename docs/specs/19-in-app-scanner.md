# 19 — In-App QR Scanner (Pemindai Kamera Web)

Status: draft v1.0 (2026-10-05) — value-add for spec 03/16; amends 15 (new role `scanner_operator`)

> **Motivation:** Physical RFID/QR gate scanners require dedicated hardware investment. Many schools start with a laptop on a desk at the gate. This spec adds a browser-based camera scanner page that reads Dynamic QR codes from students' and employees' phones, calls the existing scan API internally, and displays the result — no extra hardware needed.

## Problem

The existing attendance pipeline (spec 03) requires a dedicated hardware scanner device that reads RFID/QR and POSTs to `POST /api/attendance/scan` with a shared `X-Scanner-Key`. Schools that cannot yet afford or deploy turnstile hardware have no way to use the Dynamic QR attendance flow. Meanwhile, every school already owns at least one laptop with a built-in webcam.

Giving an existing user role (admin, teacher) direct access to the scanner key is a security concern: the key is a shared device secret, not a per-user credential. Mixing scanner-operator duties with administrative or teaching roles also clutters the sidebar and creates confusion about who is responsible for the gate.

## Goals

1. Provide a dedicated **in-app webcam scanner page** (`/scanner`) that reads Dynamic QR codes using the device camera and submits them to the backend for attendance recording.
2. Introduce a new role **`scanner_operator`** (Petugas Piket / Operator Scanner) that grants access exclusively to the scanner page and a minimal live scan log — no attendance editing, no student data, no settings.
3. Reuse the existing `AttendanceScanService` and `EmployeeScanService` pipeline so that every scan follows identical tap resolution, debounce, clock authority, and audit logging rules as hardware scanners.
4. Display clear visual and auditory feedback per scan: success (name + status), error (expired/unknown), and ignored (debounce/complete/excused/alumni).

## Non-goals

- Replacing or deprecating hardware RFID/QR scanners — this is a complement, not a substitute.
- RFID card reading from the browser (no Web NFC in this scope; RFID remains hardware-only).
- Offline/PWA buffering of scans when the network is down (same v1.x deferral as spec 03).
- Providing the scanner operator with any student/employee master data, attendance editing, or reporting capabilities.
- Multi-camera selection UI in v1 (the browser's default camera is used; most laptops have one).

## User Stories

- **As a piket teacher (petugas piket)**, I want to open my laptop at the school gate, navigate to the scanner page, and scan students' Dynamic QR codes from their phones so I can record attendance without a dedicated hardware device.
- **As a school administrator**, I want to create a dedicated scanner operator account for the gate laptop, so the operator cannot see grades, student data, or system settings.
- **As a piket teacher scanning students**, I want to immediately see the student's name and result ("Hadir", "Terlambat", "Sudah Check-in", "Kode Kedaluwarsa") on screen, so I can direct students accordingly.
- **As an administrator**, I want every in-app scan to appear in `scan_events` with `scan_method = 'dynamic_qr'`, so audit and reporting are identical to hardware scans.

## Decisions

### New role: `scanner_operator`

- Added to the `UserRole` enum: `scanner_operator` (value `'scanner_operator'`).
- **Exclusive role**: like `student`, it cannot be combined with other roles. A scanner operator account is a dedicated device login.
- **Permissions**: access to `GET /scanner` (the camera scanner page) and `POST /scanner/scan` (the internal scan submission endpoint) only. No sidebar navigation beyond "Scanner". No dashboard widgets.
- **Account provisioning**: admin creates a user with role `scanner_operator` and a strong password. The operator logs in on the gate laptop. Session timeout follows the standard Fortify session lifetime.

### Internal scan route (not the hardware API)

The in-app scanner does **not** call `POST /api/attendance/scan` from the browser. That endpoint requires the `X-Scanner-Key` header, which must never be exposed to client-side JavaScript. Instead:

- A new **authenticated web route** `POST /scanner/scan` accepts `{ credential: string }` (the decoded QR token string).
- The controller is authorized via the `scanner_operator` role (or `admin`/`teacher` if they also need access — see permission matrix below).
- Internally, the controller calls `CredentialResolver` → `AttendanceScanService`/`EmployeeScanService` with `ScanMethod::DynamicQr` and `scanned_at = now()` (server clock — the browser is not a trusted clock source).
- The response mirrors the hardware contract shape: `{ outcome, detail, student_name, subject_type }`.
- Each scan appends a `scan_events` row identically to the hardware path — same method, same outcome codes. The fact that it came from the in-app scanner vs. hardware is indistinguishable in the audit log (both are `dynamic_qr`).

### Camera access & QR decoding

- The scanner page uses a **client-side JavaScript QR decoding library** (e.g. `html5-qrcode` or `@aspect/qr-scanner`) to access the webcam via `navigator.mediaDevices.getUserMedia` and decode QR codes in real-time.
- On successful decode, the token string is sent to `POST /scanner/scan` via an Inertia `useHttp` XHR call (not a full page visit).
- A **cooldown of 2 seconds** between submissions prevents the same QR from being submitted repeatedly while still on-screen. The server-side debounce (`scan_debounce_minutes`) remains the authoritative duplicate guard.
- The page requests camera permission on mount; if denied, a clear message instructs the operator to allow camera access in browser settings.

### Scan result display

After each scan, the page shows a **result card** for 4 seconds:

| Outcome | Color | Display Text | Sound |
|---|---|---|---|
| `checked_in` (present) | Green | ✅ {name} — Hadir | Short success beep |
| `checked_in` (late) | Yellow | ⚠️ {name} — Terlambat | Short warning beep |
| `checked_out` | Blue | 🔵 {name} — Pulang | Short success beep |
| `absent_upgraded` | Yellow | ⚠️ {name} — Terlambat (Upgraded) | Short warning beep |
| `ignored_debounce` | Gray | ⏳ {name} — Sudah Tercatat | No sound |
| `ignored_complete` | Gray | ⏳ {name} — Sudah Selesai | No sound |
| `ignored_excused` | Gray | 📋 {name} — Izin/Sakit | No sound |
| `ignored_alumni` | Gray | 🚫 Alumni | No sound |
| `error_expired_token` | Red | ❌ Kode Kedaluwarsa | Error buzz |
| `error_unknown_credential` | Red | ❌ Tidak Dikenali | Error buzz |

A scrolling **scan log** below the camera feed shows the last 50 scans (session-only, not persisted — the real log is `scan_events`).

### Permission matrix amendment (spec 15)

| Capability | scanner_operator |
|---|---|
| Scanner page (`/scanner`) | ✓ |
| Scanner scan endpoint (`POST /scanner/scan`) | ✓ |
| All other capabilities | — |

`admin` and `teacher` (scoped) may also access the scanner page as a convenience, but the dedicated operator account is the primary use case.

## Requirements

1. **Scanner Page (`GET /scanner`)**:
   - Authenticated route, authorized for `scanner_operator`, `admin`, `teacher`.
   - Inertia page `pages/scanner/index.tsx`.
   - Requests camera permission via `getUserMedia({ video: { facingMode: 'environment' } })`.
   - Renders a live video feed with a scanning overlay/viewfinder.
   - On QR decode → debounce (2s client-side) → `POST /scanner/scan`.
   - Displays result card with color, icon, name, and status text.
   - Plays audio feedback (Web Audio API or short audio files; gracefully degraded if autoplay is blocked — visual feedback is always shown).
   - Session-only scan log (last 50 entries, newest first).
   - Full-screen toggle button for kiosk-style operation.
   - No sidebar navigation for `scanner_operator` beyond "Scanner" and user menu (logout).

2. **Scan Endpoint (`POST /scanner/scan`)**:
   - Authenticated web route (session auth, CSRF).
   - Authorized for `scanner_operator`, `admin`, `teacher`.
   - Request: `{ credential: string }` (required, max 255).
   - Validates credential is a non-empty string.
   - Calls `CredentialResolver::resolve(ScanMethod::DynamicQr, credential)`.
   - On student credential: delegates to `AttendanceScanService::scanResolved(...)`.
   - On employee credential: delegates to `EmployeeScanService::scanResolved(...)`.
   - On error resolution: returns the error result directly.
   - Response (JSON): `{ outcome: string, detail: string, student_name: string, subject_type: 'student'|'employee' }`.
   - `scanned_at` is always server `now()` — browser clock is not transmitted.
   - Every call produces exactly one `scan_events` row (same as hardware path).

3. **Scanner Operator Role**:
   - `UserRole::ScannerOperator` = `'scanner_operator'` added to enum.
   - Exclusive: cannot be combined with other roles (same constraint as `student`).
   - Permission: scanner page + scan endpoint only.
   - Dashboard for `scanner_operator`: redirect to `/scanner` (no dashboard widgets).
   - Sidebar: only "Scanner" link + user menu.
   - Admin user management: can create/edit users with `scanner_operator` role.

4. **JavaScript QR Library**:
   - Install `html5-qrcode` (MIT, widely maintained, ~30KB gzipped, supports `getUserMedia`).
   - Decode loop runs at camera frame rate; decoded strings are passed to the submission handler.
   - Camera is released (`MediaStream.getTracks().forEach(t => t.stop())`) on page unmount.

## Schema

No new tables. No migration required beyond the role enum extension:

- `UserRole` enum gains `ScannerOperator = 'scanner_operator'`.
- `user_roles` table already supports multiple roles; the `scanner_operator` value is simply a new enum member.
- The exclusive-role constraint (cannot combine with other roles) is enforced in the same validation logic as `student`.

## Acceptance Criteria

- **AC-19-01**: A user with role `scanner_operator` can log in, is redirected to `/scanner`, sees a camera feed, and the sidebar contains only "Scanner" and the user menu.
- **AC-19-02**: When a student's valid Dynamic QR code is held in front of the laptop camera, the scanner page decodes it, submits to `POST /scanner/scan`, and displays the student's name with "Hadir" (green card) within 2 seconds of the code appearing on screen.
- **AC-19-03**: An expired QR token (> 30s old) scanned via the in-app scanner shows "Kode Kedaluwarsa" (red card) and logs `error_expired_token` in `scan_events`.
- **AC-19-04**: Scanning the same QR code twice within the 2-second client cooldown produces only one `POST /scanner/scan` request.
- **AC-19-05**: Scanning the same student twice within `scan_debounce_minutes` (server-side) shows "Sudah Tercatat" (gray card) and logs `ignored_debounce`.
- **AC-19-06**: A `scanner_operator` user attempting to access `/attendance`, `/students`, `/settings`, or any non-scanner route receives HTTP 403.
- **AC-19-07**: An `admin` user can access `/scanner` and use the camera scanner identically to a `scanner_operator`.
- **AC-19-08**: Every scan via the in-app scanner produces exactly one `scan_events` row with `scan_method = 'dynamic_qr'`, indistinguishable from a hardware scanner event.
- **AC-19-09**: If camera permission is denied by the browser, the scanner page shows a clear instruction message instead of the video feed, and does not crash.
- **AC-19-10**: Employee QR codes are also supported: scanning an employee's Dynamic QR processes through `EmployeeScanService` and displays the employee's name.

## Constraints & Assumptions

- The browser must support `navigator.mediaDevices.getUserMedia` (all modern browsers on HTTPS).
- The page must be served over HTTPS in production (camera API requirement); `localhost` is exempt during development.
- QR code readability depends on camera quality and lighting; the spec does not guarantee performance on low-resolution webcams.
- Audio feedback may be blocked by browser autoplay policies; the page should attempt to resume the `AudioContext` on user interaction (first scan tap or a "Start Scanner" button).

## Open Questions

- `[NEEDS DECISION: Teacher scanner access scope]`: Should teachers on the scanner page be scoped to their homeroom class only, or can they scan any student? Current decision: any student (the scanner is a gate function, not a classroom function). Revisit if per-class scanners are needed.
- `[NEEDS DECISION: Scan source differentiation]`: In-app scans use the same `scan_method = 'dynamic_qr'` as hardware scans. If analytics need to distinguish the source, a new `scan_source` column (`hardware`/`web`) on `scan_events` could be added in v1.x.
