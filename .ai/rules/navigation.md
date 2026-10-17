---
paths:
    - 'resources/js/components/app-sidebar.tsx'
    - 'resources/js/pages/**'
    - 'routes/**'
---

# Navigation, Authorization & IA

## Every Surface Needs a Clickable Entry Path

Every new or updated user-facing surface must have a documented, clickable entry path in the UI for each authorized role. Direct URL access alone is an incomplete implementation.

## Primary vs Contextual Destinations

Primary destinations (top-level entity listings such as `/classes`, `/courses`, `/attendance`) belong in `resources/js/components/app-sidebar.tsx` for all applicable roles.
Contextual destinations (per-entity pages such as `/classes/{id}/report-cards`, `/courses/{id}/gradebook`) must never get a fake sidebar item that redirects to an arbitrary first record. Users reach them from the parent listing (e.g. `/classes` table actions) or an explicit entity chooser.

## Scope Data When Opening a Listing to New Roles

When a parent listing opens to new roles, scope its data (`ClassAccess::academicClassIds` for teachers) and keep mutation actions (create, edit, delete) restricted to authorized roles only.

## Verify Door to Door

UI verification for navigation changes walks the full path: login or role switch → dashboard/sidebar → parent listing → target feature.
