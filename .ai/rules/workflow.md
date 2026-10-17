---
paths:
    - '**'
---

# Workflow

## Specs Are the Source of Truth

Specs in `docs/specs/NN-*.md` define behavior; `docs/specs/README.md` holds the domain model and implementation status. Plans live in `docs/plans/`. Check the spec before changing behavior, and update its status when a feature ships.

## Run Node Through Sail

Always run Node tooling in the container (`sail npm ...`). A host `npm install` breaks the container's Vite and the app renders a blank page.

## CI Check

The full gate is `composer ci:check` (JS check, TS types, then Pint, PHPStan, artisan tests). It fails on the host because the vite-plus native binding exists only in the container, so on the host run `sail npm run check` and `sail npm run types:check`, then `composer test`. Host PHPStan needs `vendor/bin/phpstan analyse --memory-limit=1G`.

## Generated Frontend Routes

Never hand-edit `resources/js/{actions,routes,wayfinder}`; regenerate them with `sail artisan wayfinder:generate`.
