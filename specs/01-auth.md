# 01 — Authentication & Roles

Status: draft v1 (2026-09-15)

## Purpose

Accounts, login, and role-based access for the four personas on a single-school install.

## Decisions

- Public self-registration is **disabled**. Accounts are provisioned by an admin; the first admin comes from a database seeder.
- One role per user, stored as an enum on `users`: `admin`, `teacher`, `student`, `parent`. Dual roles (e.g. teacher who is also a parent) are out of scope for v1.
- Built on the starter kit's existing auth scaffolding (Inertia React + Laravel's session auth).

## Requirements

1. Login with email + password; rate-limited per Laravel defaults.
2. The registration route and UI are removed. A seeder creates the initial admin.
3. Admin can create a user with any role. The system emails that user a password-reset link to set their initial password; admins never see or set plaintext passwords.
4. Password reset via emailed link works for all roles. Email verification is not required for v1.
5. Route groups are guarded by role middleware; unauthorized access returns 403.
6. Data scoping is enforced by policies, not just navigation:
   - student → sees only their own record
   - parent → sees only their linked children
   - teacher → sees only their assigned class(es)
   - admin → sees everything
7. Logout and profile password change work for all roles (starter kit).

## Out of scope

- SSO / LDAP, 2FA, "remember device", API tokens
- Multiple roles per user
- School selection (single-school install)
