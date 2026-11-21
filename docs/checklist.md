# OolaEdNerve --- Development Checklist

**This is the only live completion checklist.** Update status only with
evidence from the current repository, tests or target environment.
Historical notes and changelog entries are not proof of current
production state.

Status: `[ ]` pending · `[~]` partial · `[L]` local verified · `[P]`
production verified · `[D]` deferred · `[?]` needs verification.

## 1. Phase 0 --- Baseline and source control

-   [ ] Confirm Git repository, remote, active branch, commit history
    and working-tree status.
-   [ ] Commit or safely back up all intended source changes; verify
    restore/clone works.
-   [ ] Audit tracked files for `.env`, credentials, dumps, logs,
    generated bundles and scratch archives.
-   [ ] Verify `.gitignore` and `.gitattributes` at repository and
    subproject levels.
-   [ ] Record PHP, Composer, Node, npm, Laravel, React, Vite, Expo and
    database versions.
-   [ ] Run API tests and web lint/build from a clean checkout.
-   [ ] Verify actual production API and web URLs, CORS and build-time
    environment variables.
-   [ ] Compare production migrations/schema with repository migrations;
    record pending migrations.
-   [ ] Reconcile documentation with current routes, models, middleware,
    services and UI.
-   [ ] Confirm no destructive migration or seed command will run
    against valuable data.

## 2. Security and operations --- P0

-   [ ] Rotate production accounts that may still use known/demo
    passwords.
-   [ ] Disable/remove demo `*.ednerve.test` accounts from pilot
    production.
-   [ ] Confirm privileged production users are enrolled in MFA and
    recovery is tested.
-   [ ] Verify MFA gates on every sensitive action actually required by
    the pilot.
-   [ ] Verify `APP_ENV=production`, `APP_DEBUG=false`, production
    secrets and least-privilege DB credentials.
-   [ ] Verify no secret is embedded in web/mobile build variables or
    repository history.
-   [ ] Verify audit coverage for institution/user/role, admission,
    examination, marks and result mutations.
-   [ ] Verify sensitive logs exclude passwords, tokens, keys and
    unnecessary student data.
-   [ ] Execute a database backup and restore drill to a separate
    scratch database.
-   [ ] Verify incident-response contact, evidence preservation and
    escalation procedure.
-   [ ] Verify production health endpoint and smoke test with a
    dedicated low-privilege account.

## 3. Architecture and shared foundations

-   [ ] Confirm Design A tenancy is the active decision: one institution
    per institution user.
-   [ ] Verify every institution-owned read/write is institution-scoped
    server-side.
-   [ ] Verify organization-unit relationships cannot cross institution
    boundaries.
-   [ ] Identify and retire dead Design-B legacy code only after
    reference and test review.
-   [ ] Verify one authoritative authorization pipeline; remove
    contradictory/unused mechanisms safely.
-   [ ] Verify role/permission catalogue and scoped permissions against
    actual schema and routes.
-   [ ] Verify audit model/table/service and event taxonomy against
    actual implementation.
-   [ ] Verify API contracts, validation, error handling and pagination
    conventions.
-   [ ] Verify shared web design system, CRUD components, tables, forms,
    filters and pagination are reused.

## 4. Platform and institution administration

-   [ ] Verify platform login, logout, session restoration and
    account-type enforcement.
-   [ ] Verify institution provisioning is transactional and creates the
    intended first administrator.
-   [ ] Verify institution activation/deactivation behaviour.
-   [ ] Verify institution profile and organization-unit CRUD.
-   [ ] Verify institution user lifecycle and token revocation on
    deactivation.
-   [ ] Verify role assignment lifecycle, effective dates and
    organization-unit scope.
-   [ ] Verify institution admin and platform admin access boundaries.
-   [ ] Verify platform and institution screens handle loading, empty,
    error and success states.

## 5. Academic and student lifecycle

-   [ ] Verify academic sessions and session status rules.
-   [ ] Verify programmes, courses/subjects and programme-course
    mappings.
-   [ ] Verify syllabus versioning and publication workflow.
-   [ ] Verify academic sections/batches model; do not create duplicate
    batch/division tables without a decision.
-   [ ] Verify course registration, elective constraints and
    registration status transitions.
-   [ ] Verify academic calendar, faculty workload and lesson-plan
    workflows.
-   [ ] Define and verify applicant → application → admission →
    enrolment → student identity.
-   [ ] Verify student profile, programme/session association and
    lifecycle transitions.
-   [ ] Verify student records are tenant-scoped and changes are
    audited.

## 6. Admissions

-   [ ] Confirm pilot institution admission regulations and approved
    category/quota definitions.
-   [ ] Define intake, programme capacity and application windows.
-   [ ] Define eligibility rules and authorised override process.
-   [ ] Define document verification and rejection/rework states.
-   [ ] Define merit calculation/import/manual preparation and
    tie-breaking.
-   [ ] Define reservation, quota, seat allocation and concurrency
    behaviour.
-   [ ] Define counselling, offer, waitlist, acceptance and withdrawal
    transitions.
-   [ ] Verify public application submission and status-by-token
    security.
-   [ ] Verify admission decisions and overrides are auditable.
-   [ ] Verify synthetic end-to-end applicant-to-enrolment tests.

## 7. Finance

-   [ ] Verify fee heads and fee structures.
-   [ ] Verify fee allocation and student ledger balances.
-   [ ] Verify manual payment and receipt workflows.
-   [ ] Verify refund/adjustment rules before pilot use.
-   [ ] Confirm accounting, GST and payment gateway are deferred or
    separately approved.
-   [ ] Verify financial mutations have authorization and audit
    coverage.

## 8. Timetable and attendance

-   [ ] Verify timetable setup and faculty/student views required by the
    pilot.
-   [ ] Verify class register and attendance corrections.
-   [ ] Verify attendance summaries and threshold calculations.
-   [ ] Verify exam eligibility integration and authorised override
    audit.
-   [ ] Confirm academic calendar/holiday interaction.

## 9. Examination and grading

-   [ ] Verify question bank and paper authoring.
-   [ ] Verify blueprint/template and paper approval/ratification
    separation of duties.
-   [ ] Verify ordinance and assessment-scheme configuration per
    institution/course.
-   [ ] Verify F4 exam scheduling and production migration state.
-   [ ] Verify F5 duty roles, availability, assignments, workload and
    production migration/seed state.
-   [ ] Define duty swap and approver separation-of-duties requirements
    if pilot needs them.
-   [ ] Verify practical/viva workflows if pilot needs them.
-   [ ] Verify marks entry, moderation, result approval and publication.
-   [ ] Verify revaluation and result-history behaviour.
-   [ ] Verify hall-ticket eligibility and privileged MFA controls.
-   [ ] Keep answer-script digitisation F8 deferred unless approved.

-   [L] F5.1 duty assignment response and history:
    Phases A-D locally implemented and verified.
    Staff acceptance/decline, assignment replacement history,
    manager restrictions and auto-assignment corrections tested.
    Backend regression baseline: 88 tests, 470 assertions,
    0 failures (21 Nov 2026).

-   [L] F5.2 cross-examination workload locally implemented and verified:
    Workload includes scheduled and published examinations in the same
    institution and academic session; completed and different-session
    examinations are excluded. Declined/replaced assignments are excluded,
    null academic sessions are limited to the target examination, and
    roster workload and auto-assignment use the same scope.
    Test result: 93 tests passed, 498 assertions, 0 failures
    (5.77 seconds).

## 10. Pilot release gate

-   [ ] Pilot scope and acceptance criteria signed off.
-   [ ] All P0 security and data-integrity actions closed.
-   [ ] Critical tenant-isolation and authorization tests pass.
-   [ ] Feature tests pass; web production build passes.
-   [ ] Pending migrations reviewed and backed up before execution.
-   [ ] Production smoke tests pass after deployment.
-   [ ] Pilot institution UAT completed with recorded defects and
    sign-off.
-   [ ] Backup restore and rollback procedure verified.
-   [ ] Operator/support and incident-response procedures available.
-   [ ] Owner gives explicit go/no-go approval.

## 11. Deferred by default

-   \[D\] Native mobile product functionality.
-   \[D\] Public website CMS.
-   \[D\] Full double-entry accounting, procurement and inventory.
-   \[D\] Payment gateway.
-   \[D\] SSO/SAML and external identity federation.
-   \[D\] Student/parent portals beyond the approved pilot slice.
-   \[D\] HR/payroll, library, hostel and transport.
-   \[D\] AI services, broad analytics/MIS and multi-language support.
-   \[D\] Examination answer-script digitisation (F8).


-   [L] Backend regression test baseline (21 Nov 2026):
    `php artisan test` — 93 tests passed, 498 assertions,
    0 failures (5.77 seconds).

Move an item out of Deferred only through an explicit scope decision and
update `plan.md`.
