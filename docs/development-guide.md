# OolaEdNerve --- Development Guide

Instructions for the project owner, developers and AI coding assistants.
Read before changing code.

## 1. Prime directives

1.  Do only the task explicitly requested. Do not opportunistically
    refactor unrelated code.
2.  Inspect the current implementation before proposing or editing it.
    Documentation may be stale.
3.  Preserve working behaviour and API contracts unless a breaking
    change is explicitly approved.
4.  Do not claim a change, deployment or test succeeded without running
    it and seeing the result.
5.  Do not run `php artisan migrate:fresh` or `migrate:refresh` against
    any database with valuable data.
6.  Never run demo seeders or use demo credentials in production.
7.  Never commit `.env`, credentials, private keys, database dumps or
    sensitive logs.
8.  No new paid service, SaaS, domain or infrastructure without owner
    approval.
9.  Do not delete, move or rename files without checking references and
    receiving approval where the change is material.
10. Keep documentation and checklist aligned with accepted code changes.

## 2. Required workflow

For every task:

1.  Read the relevant sections of `README.md`, `architecture.md`,
    `design.md` and `checklist.md`.
2.  Inspect the actual files, routes, migrations, models and tests
    affected.
3.  State the smallest proposed change and identify risks/dependencies.
4.  Implement one coherent change at a time.
5.  Run the narrowest relevant test, then the broader test/build gate.
6.  Review the diff for unrelated changes, secrets and accidental
    generated files.
7.  Update the canonical documentation and checklist with evidence.

For interactive pair-programming, work incrementally: provide one action
or test, wait for its output, then decide the next step. Do not assume
commands ran or files changed.

## 3. Backend rules --- Laravel

-   Follow existing namespaces, validation, middleware, service, policy
    and resource conventions.
-   Derive tenant identity from authenticated server-side context; do
    not trust client-provided tenant IDs.
-   Validate all related tenant-owned records, including
    organization-unit relationships.
-   Apply authorization on every API request; frontend hiding is not
    security.
-   Use database transactions for multi-step state changes.
-   Use additive migrations for deployed schema changes; preserve
    migration history.
-   Do not edit an already-applied migration to change production
    schema.
-   Add feature tests for authentication, authorization, tenant
    isolation, validation and workflow transitions.
-   Use explicit status values consistent with the actual schema;
    inspect migrations before assuming column names or types.
-   Keep secrets and sensitive payloads out of logs and audit metadata.

## 4. Web rules --- React/Vite

-   Follow the existing React Router and feature-module structure.
-   Use the shared API client and service layer; no ad hoc `fetch` calls
    in components.
-   Use TanStack Query patterns already adopted for server state.
-   Reuse shared design-system primitives and CRUD components.
-   Keep styles in the shared design system or the owning feature
    module; avoid duplicate page-level frameworks.
-   Handle loading, empty, error, validation and success states.
-   Keep route/navigation metadata separate from backend authorization.
-   Do not place secrets in `VITE_*` variables; all client-side build
    variables are public.

## 5. Mobile rules --- Expo

Mobile product work is deferred unless explicitly approved. If
approved: - Inspect the installed Expo SDK and use matching official
documentation. - Use `npx expo install` for native dependencies. - Keep
file-based routes in the configured app route directory. - Run
TypeScript, Expo lint and Expo Doctor checks appropriate to the project.

## 6. Data and security rules

-   Use synthetic data for development and tests.
-   Do not paste real student records, exam papers, credentials, private
    keys or production database dumps into external AI tools.
-   Redact logs and examples before sharing.
-   Protect sensitive documents with authorization, tenant scoping and
    controlled download access.
-   Record material mutations with consistent audit events, but do not
    log secrets.
-   Review retention, consent and disclosure requirements with the
    institution and appropriate legal/compliance advice; do not assume
    one fixed retention period applies universally.

## 7. Definition of done

-   [ ] Requirements and acceptance criteria met.
-   [ ] Tenant isolation and authorization enforced server-side.
-   [ ] Relevant automated tests pass.
-   [ ] API and web builds/lint/type checks pass as applicable.
-   [ ] No unrelated code, secret or generated artifact added.
-   [ ] Error/loading/empty/success states handled.
-   [ ] Audit and sensitive-data implications addressed.
-   [ ] Documentation and checklist updated.
-   [ ] Deployment/migration/rollback impact recorded.
