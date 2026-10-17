---
paths:
    - 'app/**'
    - 'database/**'
---

# Data

## Class Membership Comes Only From Enrollments

A student's class on a date is resolved through `enrollments` (`classOn(date)`). Never add a class pointer to students, attendance rows, or any other table.

## Write Queries That Behave the Same on PostgreSQL

Tests run on sqlite, production on PostgreSQL, and sqlite hides two differences:

- Plain `'like'` is case-sensitive on PostgreSQL. Use `whereLike` for user-facing search. Older list pages (students, teachers, guardians, …) still use `'like'`.
- An unknown column in an eager-load select (`student:id,name`) does not fail on sqlite; it becomes a string literal. Check column names against the schema.
