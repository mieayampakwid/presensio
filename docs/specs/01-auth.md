# 01 — Authentication & Roles

Status: v1.0 — implemented 2026-09-19 (amended 2026-09-25)

## Problem

Indonesian schools operate in an environment where email is rarely the primary identity anchor. Primary/secondary school students and many guardians do not possess or actively monitor email addresses, while teachers and staff identify institutionally by official numbers (NIP, NUPTK). Standard web application authentication relying on email login and email-only password resets creates friction, high support overhead, and low adoption. Furthermore, public self-registration is unacceptable for a school system where every user must correspond to an enrolled student, employed teacher, or verified legal guardian.

## Goals

1. Authenticate four distinct school personas (`admin`, `teacher`, `student`, `parent`) using institutional identifiers (`username` = NIP/NIS/NIK) instead of emails.
2. Maintain strict separation of concerns between authentication credentials (`users` table) and domain profile master data (`teachers`, `students`, `guardians`).
3. Prevent unauthorized account creation by disabling public registration; all accounts are provisioned by an administrator.
4. Support password recovery through WhatsApp and email, looking up the destination contact details dynamically from the user's linked master profile.
5. Enforce strict role-based authorization and data scoping so that users access only records relevant to their role.

## Non-goals

- Public self-registration (invite-code claim flow for parents is a v1.x candidate; see Open Questions).
- Multi-role assignments per user (e.g., a teacher who is also a parent has one primary role in v1).
- Multi-tenancy or multi-school selection (designed for single-school deployment).
- SSO / LDAP / OAuth2 (Google/Microsoft Workspace) / SAML integration.
- Hardware 2FA / WebAuthn / Passkeys in default user flows (schema columns present via Laravel Fortify, but deferred to v1.x).
- API token issuing (Sanctum/Passport personal access tokens) for end-users.

## User Stories

- **As an admin**, I want to provision user accounts for teachers, students, and guardians with their respective identity numbers as usernames, so that users can log in immediately with pre-distributed credentials.
- **As a teacher or student**, I want to log in using my NIP or NIS and password, so that I do not need a personal email to access the system.
- **As a parent who forgot my password**, I want to request a reset using my username and receive a secure reset link via WhatsApp (or email fallback), so that I can regain access without manual admin intervention.
- **As a logged-in user**, I want to change my own password, but I cannot tamper with my username or assigned role.
- **As an admin**, I want to deactivate a user account instantly (`is_active = false`), so that former employees or withdrawn students cannot access school records.

## Decisions

- **Separation of Profile and Login**: The `users` table is exclusively an authentication credential store. Domain attributes (names, birth dates, phone numbers, addresses, employment numbers) reside strictly in `teachers`, `students`, and `guardians` master tables (spec 02).
- **Canonical Schema Authority**: Spec 01 is the single source of truth for the `users` table schema. Other specs must reference this definition.
- **Login Identifier**: `username` stores the user's institutional identity number (NIP/NIK/NIS). It is case-insensitive and trimmed upon lookup.
- **No Public Registration**: Registration routes (`/register`) are disabled. The initial system administrator is provisioned via the dedicated Artisan command (`php artisan app:create-admin {username} {--email=} {--password=}`).
- **One Role Per User**: User roles are an immutable enum (`admin`, `teacher`, `student`, `parent`). Dual roles are handled by separate accounts in v1.
- **Contact Lookup for Resets**: When a user requests a password reset, the system resolves the linked master profile based on the user's role and retrieves `phone_number` or `email` at runtime.
- **Rate Limiting**: Password-sensitive flows (`forgot-password` store/update, profile password update) are throttled to 6 attempts per minute (`throttle:6,1`); login attempts are throttled by Fortify's built-in login limiter. Exceeding a limiter returns HTTP 429 Too Many Requests with a `Retry-After` header.

## Requirements

1. **Login Flow**:
   - Accepts `username` and `password`.
   - Rejects unauthenticated attempts with HTTP 422 (`These credentials do not match our records`).
   - Rejects inactive accounts (`is_active = false`) with HTTP 403 or specific message (`Akun Anda dinonaktifkan. Hubungi administrator`).
   - Rate-limited to max 5 failed attempts per minute.
2. **Registration Disabled**:
   - GET `/register` and POST `/register` must return HTTP 404.
   - Initial administrative user is provisioned via `php artisan app:create-admin`.
3. **Admin User Provisioning**:
   - Admin can create a user record with `username`, `role`, and temporary `password`.
   - Creating a user allows atomic 1:1 linking with a profile in `teachers`, `students`, or `guardians`.
4. **Password Reset Flow**:
   - User inputs their `username` at `/forgot-password`.
   - System checks if user exists and is active; if not, responds with a generic success message to prevent user enumeration.
   - System resolves linked profile:
     - `teacher` -> `teachers.phone_number` / `users.email`
     - `parent` -> `guardians.phone_number` / `guardians.email` (or `users.email`)
     - `student` -> no self-service reset in v1 (must be reset by admin or class teacher)
   - Dispatches a time-limited signed reset URL (valid for 30 minutes) via WhatsApp if phone exists, falling back to email if configured.
5. **Profile Modification Constraints**:
   - Authenticated users may update their password via `PUT /user/password` (requires current password confirmation).
   - Authenticated users **cannot** update their own `username`, `role`, or `is_active` status.
   - Exception: Logged-in guardians may update their own profile contact details (`phone_number`, `address`, `work`) via guardian profile endpoints (spec 02).
6. **Authorization & Data Scoping**:
   - `admin`: Unrestricted access across all classes, students, attendances, and system settings.
   - `teacher`: Scoped to homeroom classes assigned to them in the active academic year, plus classes where they are assigned as subject teachers (spec 09).
   - `parent`: Scoped strictly to students linked through the `guardian_student` pivot table.
   - `student`: Scoped strictly to their own attendance records and personal profile.
   - Any access attempt outside authorized scope returns HTTP 403 Forbidden.

## Schema

### `users` Table (Canonical Definition)

| Column | Type | Modifiers | Description |
|---|---|---|---|
| `id` | bigint | unsigned, primary key, auto-increment | Internal identifier |
| `username` | varchar(255) | unique, not null | Login identity number (NIP/NIS/NIK) |
| `email` | varchar(255) | unique, nullable | Optional fallback email |
| `password` | varchar(255) | not null | Bcrypt / Argon2 hashed password |
| `role` | varchar(20) | not null | Enum: `admin`, `teacher`, `student`, `parent` |
| `is_active` | boolean | not null, default: true | Account active flag |
| `remember_token` | varchar(100) | nullable | Remember token |
| `created_at` | timestamp | nullable | Record creation timestamp |
| `updated_at` | timestamp | nullable | Record update timestamp |

**Indexes:**
- `UNIQUE (username)`
- `UNIQUE (email)`

## Acceptance Criteria

- **AC-01-01**: Given valid credentials of an active user, when POST `/login` is submitted, then the session is created and the user is redirected to `/dashboard`.
- **AC-01-02**: Given an inactive user (`is_active = false`), when POST `/login` is submitted with valid password, then login fails with message indicating account is inactive.
- **AC-01-03**: Given 5 consecutive invalid login attempts for a username/IP within 60 seconds, when the 6th attempt is made, then HTTP 429 is returned.
- **AC-01-04**: When GET `/register` is requested, the system returns HTTP 404.
- **AC-01-05**: Given a logged-in teacher, when they attempt to query attendance data of a class not assigned to them, then HTTP 403 Forbidden is returned.
- **AC-01-06**: Given a logged-in parent, when they attempt to query child reports of an unlinked student ID, then HTTP 403 Forbidden is returned.
- **AC-01-07**: Given a logged-in user, when they attempt to update their `role` or `username` via profile endpoints, the request is rejected with validation error or 403.

## Constraints & Assumptions

- System runs on PHP 8.5 / Laravel 12 with Laravel Fortify handling headless authentication primitives.
- Password policy (production): minimum 12 characters with mixed case, letters, numbers, symbols, and a compromised-password check (`Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()`); not enforced outside production.
- Single-school install assumption: username uniqueness is global within the instance.

## Open Questions

- `[NEEDS DECISION: Password Reset via WhatsApp Provider]`: In v1, WhatsApp reset URL dispatch relies on the notification gateway (spec 05). If no WhatsApp provider is configured, password reset falls back to email. If neither exists, admin manual reset via UI is the only path.
- `[NEEDS DECISION: Guardian Invite Code Self-Claim (v1.x)]`: School distributes unique 8-character claim codes per student. Guardians register with code + child birth date + phone number. Deferred to v1.1.
