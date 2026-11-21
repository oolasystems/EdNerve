# OolaEdNerve --- Quality, Testing and Operations

This document consolidates the test strategy, deployment guidance and
essential security runbooks. Verify commands and hosting capabilities
against the current repository and MilesWeb account before execution.

## 1. Test principles

-   Test behaviour at the HTTP boundary for authentication,
    authorization, tenant isolation and domain workflows.
-   Add tests with the feature, not after it.
-   Prefer deterministic synthetic fixtures and a dedicated test
    database.
-   Never point automated tests at production or a developer's valuable
    database.
-   Keep the suite fast enough to run on every meaningful change.
-   A green local suite does not prove production deployment or
    production data correctness.

## 2. Minimum test coverage

### API

-   Login, logout, `/auth/me`, inactive user/institution and token
    revocation.
-   Platform-only and institution-only endpoint access.
-   Cross-institution read/write denial for every institution-owned
    resource.
-   Organization-unit scope and related-record consistency.
-   Validation, pagination and expected error responses.
-   Transaction rollback for multi-record operations.
-   Audit events for material mutations.
-   Domain happy paths and negative transitions for each pilot workflow.

### Web

-   Production build and type-check.
-   Authentication restoration and unauthorized redirect.
-   Key route rendering for each pilot workflow.
-   Loading, empty, error and validation states.
-   API integration and role-based navigation behaviour.

### Release smoke tests

Use explicit credentials from a dedicated, low-privilege test account.
Never hardcode passwords in scripts or documentation. Verify health,
login, current-user context and the pilot endpoints/pages.

## 3. Local test safety

-   Use a dedicated test database, for example `ednerve_db_test`.
-   Confirm `.env.testing`/`phpunit.xml` point to the test database or
    intended in-memory SQLite configuration.
-   Inspect test configuration before running destructive test traits.
-   Do not run tests against `ednerve_db` or production.
-   Record the command and result when updating checklist status.

Typical commands (confirm scripts exist in the current project):

``` powershell
# API
cd ednerve-api
php artisan test

# Web
cd ..\ednerve-web
npm run lint
npm run build

# Mobile — only when mobile work is approved
npx tsc --noEmit
npx expo lint
npx expo-doctor
```

## 4. Deployment controls

Before deployment:

1.  Confirm source commit and target branch.
2.  Confirm API and web build artifacts correspond to the same intended
    release.
3.  Back up the production database and verify the backup is readable.
4.  Review pending migrations and their forward/rollback implications.
5.  Confirm production `.env`, `APP_ENV=production`, `APP_DEBUG=false`,
    credentials and CORS.
6.  Confirm no demo seeders or test accounts will be run or exposed.
7.  Confirm privileged MFA and audit controls for the release's
    workflows.

Deployment rules:

-   Apply reviewed migrations using the approved production procedure
    (normally `php artisan migrate --force`).
-   Never run `migrate:fresh`, `migrate:refresh` or broad demo seeding
    in production.
-   Do not mix manual SQL and Laravel migration execution for the same
    schema change unless the migration ledger is reconciled
    deliberately.
-   Deploy API and web in a controlled order appropriate to backward
    compatibility.
-   Run post-deploy smoke tests and inspect logs; if a critical check
    fails, stop and investigate rather than declaring success.

## 5. Secrets and environment

-   `.env` files remain outside source control.
-   Database credentials, `APP_KEY`, mail credentials and
    signing/private keys remain server-side.
-   `VITE_*` and `EXPO_PUBLIC_*` values are public and must contain no
    secrets.
-   Rotate any credential that may have been committed or disclosed.
-   Use least-privilege database accounts and separate
    local/test/production credentials.
-   Do not print secrets in terminal transcripts, logs, screenshots or
    support requests.

## 6. MFA, authorization and audit

Before pilot use, verify in the actual production environment:

-   Privileged accounts are enrolled in MFA and recovery codes are
    safely stored.
-   Sensitive actions that require step-up authentication are actually
    gated server-side.
-   Role and organization-unit scope is enforced by the API.
-   Audit events identify actor, action, target and time without storing
    secrets or unnecessary sensitive values.
-   Audit records cannot be altered through ordinary application
    workflows.
-   Production configuration matches the intended policy.

## 7. Backup and recovery

-   Back up the database before every production migration and on a
    scheduled cadence.
-   Keep a protected off-server copy where feasible.
-   Protect backup files as highly sensitive data.
-   Perform periodic restore drills into a separate
    database/environment.
-   Verify restored schema, record counts and critical application
    workflows.
-   Record backup date, location, restore result and operator.
-   A backup that has never been restored is not a verified recovery
    plan.

## 8. Incident response

For a suspected security or data incident:

1.  Preserve evidence and record time, affected systems and observed
    symptoms.
2.  Contain the incident without destroying evidence.
3.  Revoke exposed credentials/tokens and isolate affected
    accounts/systems as appropriate.
4.  Assess data and tenant impact; preserve relevant logs and audit
    records.
5.  Restore from a known-good backup only after the cause and integrity
    are understood.
6.  Notify the appropriate institution/parties under the applicable
    contractual and legal process.
7.  Record root cause, corrective actions and follow-up tests.

Do not assume a generic notification timeline or retention period;
confirm applicable obligations with qualified counsel and the
institution.

## 9. Go-live gate

A pilot release is not ready until: - Critical tenant-isolation and
authorization tests pass. - Production credentials and demo accounts are
cleaned up. - Privileged MFA is verified. - Backup and restore have been
demonstrated. - Production smoke tests pass. - Pilot UAT and owner
sign-off are recorded. - Incident, support and rollback procedures are
available.
