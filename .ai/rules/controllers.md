---
paths:
    - 'app/Http/Controllers/**'
---

# Controllers

## Keep Controllers Skinny and Focused on HTTP Concerns

Controllers must only coordinate HTTP requests, authorization, and validation, delegating business operations to Services, Builders, or Actions before returning responses. Never place raw SQL (selectRaw/whereRaw), complex aggregations, lengthy manual DTO transformations, or multi-step database transactions directly in controllers.
