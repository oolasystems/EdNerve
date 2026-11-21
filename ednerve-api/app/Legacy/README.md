# Legacy (quarantined) code — DO NOT WIRE

These classes reference a **schema that does not exist** in the current migrations
(`institution_user` pivot, `institutions.is_active`, `is_active` booleans, `roles.institution_id`).
They were written for a multi-institution membership design ("Design B"), while the
codebase implements a simpler single-institution model ("Design A": `users.institution_id`).

- `InstitutionContext.php` — designed for `users.institutions()` many-to-many with pivot columns
  `membership_status` and a `institutions.is_active` flag. Neither exists; any call fatals.
- `RolePermissionResolver.php` — expects `permissions` / `role_permission` tables with
  `is_active` columns. Neither exists; any call fatals.

**Status:** quarantined 2026-11-15 during the OWASP/authorization review. Not referenced
by routes, controllers, middleware or providers anywhere in the codebase. The namespace
changed to `App\Legacy`, so even an accidental reference fails loudly at autoload time.
Sources are byte-identical to `HEAD` (commit `68ee321`) except for the namespace line.

**History note:** during that same review these files were accidentally truncated and
recovered; final state in `app/Legacy/` equals the committed versions (namespace aside).
`app/api.zip` (a snapshot artifact) was removed from the repo earlier at the owner's
request (task T-002) and no longer exists.

**Restoring:** if decision **D1** (see `docs/open-decisions.md`) selects Design B, treat
these files as reference only — rework against the real schema, cover with tests, and
delete or properly implement.
