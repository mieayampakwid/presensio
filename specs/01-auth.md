# 01 — Authentication & Roles

Status: final v1 (2026-09-16)

## Purpose

Accounts, login, and role-based access for the four personas on a single-school install.

## Decisions

- Public self-registration is **disabled**. Accounts are provisioned by an admin; the first admin comes from a database seeder.
- One role per user, stored as an enum on `users`: `admin`, `teacher`, `student`, `parent`. Dual roles (e.g. teacher who is also a parent) are out of scope for v1.
- Built on the starter kit's existing auth scaffolding (Inertia React + Laravel's session auth).
- **Login Identifier Replacement:** Users will log in using a unique `identification_number` instead of an email address.
- **WhatsApp Integration:** The system leans towards WhatsApp for notifications, therefore a WhatsApp-linked phone number is prioritized alongside (or in place of) email for password reset flows.

## Requirements

1. **Login:** Users authenticate with `identification_number` + password; rate-limited per Laravel defaults.
2. **Account Status:** Inactive users (e.g., alumni or resigned teachers) are prevented from logging in via an `is_active` flag. (Login attempt returns an error).
3. **Registration Disabled:** The registration route and UI are removed. A seeder creates the initial admin user.
4. **User Provisioning:** Admin creates a user with a `role`, `identification_number`, `phone_number` (WhatsApp), and optionally `email`. The system sends a password-reset link (or initial setup code) via WhatsApp/Email to set their initial password. Admins never see plaintext passwords.
5. **Password Reset:** The "Forgot Password" form asks for the `identification_number`. The system then looks up the associated `phone_number`/`email` and sends a reset link/code to them.
6. **Profile Updates Restricted:** Users can only change their password and standard details. They **cannot** modify their `identification_number` or `role` (this is constrained to Admin management only).
7. **Access Control:** Route groups are guarded by role middleware; unauthorized access returns 403.
8. **Data Scoping:** Enforced by policies, not just navigation:
   - student → sees only their own record
   - parent → sees only their linked children
   - teacher → sees only their assigned class(es)
   - admin → sees everything
9. **Database Schema:** The `users` table must include:
   - `identification_number` (string, unique)
   - `role` (enum: admin, teacher, student, parent)
   - `phone_number` (string, unique, nullable) - for WhatsApp integration
   - `is_active` (boolean, default: true)
   - `email` (string, nullable, unique) - retained as a fallback.

## Out of scope

- SSO / LDAP, 2FA, "remember device", API tokens
- Multiple roles per user
- School selection (single-school install)
