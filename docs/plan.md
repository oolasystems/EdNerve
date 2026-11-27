# OolaEdNerve --- Development Plan

**Planning approach:** dependency-first, incremental delivery, zero
additional spend until the MVP is ready for
pilot/sponsorship/investment. This plan deliberately avoids treating
historical percentage estimates as a reliable measure of completion.

## 1. Delivery objective

Deliver a secure, tenant-isolated higher-education ERP pilot that one
institution can use for agreed end-to-end workflows, with a repeatable
deployment and recovery process.

The owner must confirm the pilot institution, the first operational
workflows, the acceptance date and any regulatory constraints before
feature priorities are locked.

## 2. Delivery phases

### Phase 0 --- Repository and environment baseline

-   Confirm the actual Git state, branches, remotes and committed
    source.
-   Confirm local API, web and database versions and clean build/test
    commands.
-   Verify production API/web URLs, environment configuration, CORS,
    PHP/runtime limits and database state.
-   Reconcile documentation against current migrations, routes, models,
    services and deployed build.
-   Resolve or explicitly park every critical architecture decision.
-   Protect secrets and ensure no production data is used in local
    tests.

**Exit gate:** recoverable source control, reproducible local setup,
known production baseline and no unresolved architecture contradiction
that affects the next feature.

### Phase 1 --- Platform and institution foundation

-   Verify platform authentication and institution provisioning.
-   Verify institution authentication, user lifecycle, organization
    hierarchy and role assignments.
-   Verify tenant isolation on every institution-owned endpoint.
-   Verify the actual authorization pipeline and remove/retire dead
    legacy code only after references are checked.
-   Confirm audit coverage for privileged mutations.
-   Complete institution administration UX using the shared design
    system.

**Exit gate:** an institution administrator can perform the agreed
administration tasks without cross-tenant access.

### Phase 2 --- Pilot academic and student lifecycle

-   Verify academic sessions, programmes, subjects/courses, syllabus and
    sections.
-   Verify course registration and academic
    calendar/workload/lesson-plan features reported as locally
    implemented.
-   Confirm applicant-to-enrolment-to-student identity and lifecycle.
-   Complete only the student profile and records required by the pilot.
-   Validate all data relationships against current schema before
    introducing new tables.

**Exit gate:** a pilot student can be represented consistently from
admission/enrolment through academic registration.

### Phase 3 --- Pilot admissions

-   Confirm the pilot's admission policy and approved rules.
-   Complete eligibility, document verification, merit/counselling,
    quota/seat allocation and waitlist only as required.
-   Deliver the institution-branded public admission landing page.
-   Deliver applicant registration, email verification, password setup
    and applicant login using the verified backend contract.
-   Deliver the authenticated applicant workspace and account-based
    application draft, review and submission lifecycle.
-   Deliver public, read-only application tracking by application number
    and date of birth; do not expose anonymous application creation or
    mutation.
-   Keep public credential verification as a separate journey.
-   Preserve and regression-test the Oola Systems iframe wrapper and
    embedded SPA navigation.
-   Ensure seat allocation is concurrency-safe and auditable.
-   Test the complete path with synthetic data before pilot data is
    introduced.

**Exit gate:** an applicant can move through the approved admission
process to enrolment, with traceable decisions.

### Phase 4 --- Pilot examination and assessment

-   Verify the production status of existing question bank,
    paper-setting, approval/ratification and F4 scheduling.
-   Verify F5 duty roster migrations, seed data, API and web deployment;
    implement only missing approved workflows.
-   Prioritise registrar-critical assessment, marks, result
    approval/publication and revaluation paths.
-   Confirm attendance/eligibility integration and privileged MFA gates.
-   Keep answer-script digitisation (F8) deferred unless approved.

**Exit gate:** the pilot can conduct the agreed examination cycle with
controlled access and auditable results.

### Phase 5 --- Pilot readiness and controlled onboarding

-   Close production security hygiene actions.
-   Complete backup and restore drill.
-   Enrol and verify privileged-user MFA.
-   Execute production smoke tests and institution UAT.
-   Confirm support, incident response, rollback and data handling.
-   Onboard one pilot institution with real data only after the go/no-go
    decision.

**Exit gate:** owner signs off on security, data integrity, UAT and
operational readiness.

### Later --- post-pilot expansion

Potential later work: full accounting/double-entry, payment gateway,
notifications, reports/MIS, HR, library, hostel, transport, CMS,
student/parent portals, mobile, SSO, AI and advanced integrations. These
are not automatic commitments.

### Phase 3A --- Admissions applicant experience implementation order

Deliver the applicant-facing work in the following dependency order.
This is a target sequence, not a claim of current implementation status.

1.  **Contract and route audit:** inspect Laravel applicant/admission
    routes, controllers, models, migrations, policies, tests and current
    React router/API/auth code. Record verified request/response
    contracts and distinguish active routes from unused legacy pages.
2.  **Shared design system:** confirm reusable public/authenticated
    layouts, typography, form controls, validation, alerts, buttons,
    cards, status badges, loading/empty/error states and responsive
    behaviour.
3.  **Institution-branded landing:** render validated institution
    branding and provide registration, login and tracking entry points.
4.  **Account lifecycle:** implement registration, email verification,
    password setup/activation, resend verification and applicant login.
    Confirm token expiry, resend limits and the actual backend flow.
5.  **Authenticated workspace:** implement dashboard, profile,
    application list/details and ownership-safe navigation.
6.  **Application lifecycle:** implement draft creation, editing, review
    and explicit submission, enforcing all state transitions on the API.
7.  **Public tracking:** implement application-number plus date-of-birth
    lookup via POST, institution scoping, rate limiting, generic
    mismatch behaviour and minimal public response fields.
8.  **Credential verification:** retain as a separate public capability.
9.  **Embedded integration regression:** verify origin/source
    validation, iframe resize behaviour, wrapper compatibility and
    internal SPA navigation without unnecessary top-level reloads.
10. **Legacy cutover:** only after replacement behaviour is verified,
    disable/remove anonymous submission routes and orphaned code after
    checking references, tests, links and production usage.

Do not implement all screens in one unverified batch. Complete and test
one coherent slice at a time.

## 4. Dependency and sequencing rules

1.  Do not build a feature on an unresolved tenant or authorization
    design.
2.  Do not implement admissions allocation before admission rules and
    the existing academic-section model are confirmed.
3.  Do not expand examinations until the current F4/F5 deployment and
    migration state is verified.
4.  Do not add file upload features before storage, access control,
    retention and backup are specified.
5.  Do not add notifications independently inside modules; use a shared
    service when notification delivery is approved.
6.  Build and test API contracts before consuming them in the web app;
    mobile follows only if approved.
7.  Prefer one complete vertical slice over several partially
    implemented modules.

## 5. Immediate priority order

1.  **P0 --- Verify baseline:** Git/source backup, repository state,
    migrations, API/web production parity, secrets and critical known
    issues.
2.  **P0 --- Security operations:** rotate any remaining known/demo
    credentials, remove demo accounts from pilot production, verify
    privileged MFA, execute restore drill.
3.  **P1 --- Architecture reconciliation:** tenant boundary,
    authorization pipeline, audit implementation, current database model
    and deployment configuration.
4.  **P1 --- Pilot definition:** choose institution, workflows,
    regulations, users and acceptance criteria.
5.  **P1 --- Feature verification:** verify academic, admissions,
    finance, attendance and examination claims against code and
    production.
6.  **P2 --- Implement only the gaps required for the selected pilot
    workflows.**

## 6. Planning and change control

For each task, record: ID, priority, owner (normally project owner),
dependency, expected outcome, acceptance test, status and evidence.

A scope change must state: - Why the change is needed for the pilot -
What existing work it reuses - Data/schema/API impact - Security and
tenant-isolation impact - Time/cost impact - What is deferred in
exchange

Do not maintain a separate completion percentage unless the scope,
weights and evidence are defined and updated consistently. The checklist
is the live status record.
