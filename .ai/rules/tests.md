---
paths:
  - 'tests/**'
---

# Tests

## Keep test ownership and coverage focused
Before adding tests or files, inspect existing coverage and extend the file owning the behavior; create a new file only for a distinct responsibility. Each test must catch a distinct consumer-visible defect; different input alone is not distinct coverage.
Keep cross-endpoint workflows minimal and reserved for integration risks; leave validation matrices and edge cases in focused owner tests. Use only necessary fixtures and avoid growing shared setup for a single scenario or introducing base classes/traits merely to save a few lines.
Assertions must prove the named behavior: write operations need relevant persisted state or observable effects, and rejected writes must leave that state unchanged. Judge quality by focus, duplication, and failure diagnosis—not arbitrary line or assertion limits. Reuse testing-best-practices instead of duplicating its framework guidance.
