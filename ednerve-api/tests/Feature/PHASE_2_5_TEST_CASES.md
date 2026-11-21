# Phase 2.5 – Authorization and Institution-Isolation Test Cases

These are integration test scenarios to execute after the route and middleware wiring. They deliberately do not assume a particular factory or seeder implementation.

| # | Scenario | Expected result |
|---|---|---|
| 1 | Unauthenticated request to a protected route | HTTP 401 |
| 2 | Authenticated user without active institution context calls institution-scoped route | HTTP 422 `INSTITUTION_CONTEXT_REQUIRED` |
| 3 | User has active membership and required permission in selected institution | Request reaches controller |
| 4 | User has no required permission | HTTP 403 `PERMISSION_DENIED` |
| 5 | User has permission only in Institution A but selects Institution B | HTTP 403 |
| 6 | Organization unit belongs to selected institution and user has matching scoped permission | Request reaches controller |
| 7 | Organization unit belongs to a different institution | HTTP 403 `ORGANIZATION_UNIT_OUT_OF_SCOPE` |
| 8 | Non-numeric or zero organization-unit identifier | HTTP 422 `INVALID_ORGANIZATION_UNIT` |
| 9 | Organization-wide role assignment is used with a valid unit in the same institution | Permission is granted if active |
| 10 | Organization-specific role assignment is used with a different unit | HTTP 403 |
| 11 | Assignment is inactive, not yet effective, or expired | HTTP 403 |
| 12 | Role or permission is inactive | HTTP 403 |

## Data-isolation assertion

Permission middleware does not filter records. Every institution-owned query in a controller/service must still include the validated institution ID:

```php
$institutionId = (int) $request->attributes->get('institution_id');

$records = ExampleResource::query()
    ->where('institution_id', $institutionId)
    ->paginate();
```

For organization-scoped records, also constrain `academic_unit_id` using the validated request attribute. Never use a raw institution or organization-unit ID from the request as the query boundary.
