---
paths:
    - 'resources/js/**'
    - 'app/Http/Controllers/**'
    - 'app/Http/Middleware/HandleInertiaRequests.php'
---

# Inertia

## Do Not Shadow Shared Props

Never reuse a shared Inertia prop name (`notifications`, `auth`, `translations`, …) as a page prop; the page prop silently replaces the shared value for that page.

## Read Flash Data From page.flash

`Inertia::flash()` data arrives on `usePage().flash`, not `usePage().props.flash`. Reading it from props always yields `undefined`.
