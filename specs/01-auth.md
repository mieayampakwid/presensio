# 01 — Authentication & Roles

Status: draft v1.1 (2026-09-16)

## Purpose

Accounts, login, and role-based access for the four personas on a single-school install.

## Decisions

- **Separation of Profile and Login**: The `users` table acts exclusively as an authentication credential store. Extended profile details (like phone numbers or addressing) live in `students`, `teachers`, and `guardians` master data tables (see spec 02).
- Public self-registration is **disabled**. Accounts are provisioned by an admin; the first admin comes from a database initialization script.
- One role per user, stored as an enum on `users`: `admin`, `teacher`, `student`, `parent`. Dual roles are out of scope for v1.
- **Login Identifier:** Users log in using a unique `username` (which acts as the identity_number: NIP/NIK/NIS) instead of an email address.
- **WhatsApp Integration:** The system leans towards WhatsApp for notifications. Authentication flows (like password reset) will dynamically fetch the associated `phone_number` from the linked master profile (`guardians` or `teachers`), prioritizing it alongside email.

## Requirements

1. **Login:** Users authenticate with `username` + password; endpoints must be protected by standard rate-limiting against brute force attacks.
2. **Account Status:** Inactive users are prevented from logging in via an `is_active` flag. (Login attempt returns an error).
3. **Registration Disabled:** The registration route and UI are removed. An initial setup script creates the administrative user.
4. **User Provisioning:** Admin creates a user with a `role`, `username`, and password. The system links this user account to the respective master profile (`teacher`, `guardian`, or `student`).
5. **Password Reset:** The "Forgot Password" form asks for the `username`. The system looks up the user's role, fetches the linked profile to get the target `phone_number` or `email`, and sends a reset link/code via WhatsApp/Email. 
6. **Profile Updates Restricted:** Users can only change their password. They **cannot** modify their `username` or `role` (constrained to Admin).
7. **Access Control:** Route groups and API endpoints are guarded by role-based access interceptors; unauthorized access returns 403 Forbidden.
8. **Data Scoping:** Enforced by application-level authorization rules:
   - student → sees only their own record
   - parent → sees only children linked via `guardian_student` pivot
   - teacher → sees only their assigned class(es)
   - admin → sees everything
9. **Database Schema Constraint:** The `users` table must be lean:
   - `id`
   - `username` (string, unique) - acts as identity_number
   - `email` (string, nullable, unique) - fallback
   - `password`
   - `role` (enum: admin, teacher, student, parent)
   - `is_active` (boolean, default: true)

## Out of scope

- SSO / LDAP, 2FA, "remember device", API tokens
- Multiple roles per user
- School selection (single-school install)