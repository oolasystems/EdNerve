# OolaEdNerve --- Technical Architecture

**Status:** working architecture baseline consolidated from the supplied
project documents. Confirm implementation details against the current
repository before changing code or deploying.

## 1. Product and architecture direction

OolaEdNerve is a multi-institution higher-education ERP operated by Oola
Systems.

-   Architecture style: **modular monolith**.
-   Tenant boundary: **one tenant = one university or college**.
-   Organization units (faculties, schools, departments, offices,
    campuses) are divisions within one institution; they are not
    tenants.
-   Backend authorization and tenant isolation are authoritative.
    Frontend route and navigation guards are defence in depth only.
-   Prefer explicit, maintainable designs over premature abstraction or
    microservices.
-   Keep development and MVP operating costs at zero additional spend
    unless the owner approves otherwise.

## 2. Technology baseline

  ---------------------------------------------------------------------
  Layer                              Baseline recorded in project
                                     documents
  ---------------------------------- ----------------------------------
  API                                Laravel 13, PHP 8.3, Sanctum

  Database                           MySQL/MariaDB

  Web                                React 19, TypeScript, Vite, React
                                     Router, Tailwind CSS 4

  Web data                           Axios/API client, TanStack Query;
                                     React Hook Form/Zod where already
                                     adopted

  Mobile                             React Native + Expo Router;
                                     scaffold only, product scope
                                     deferred pending explicit decision

  API hosting                        MilesWeb shared hosting

  Web hosting                        Cloudflare Pages/Workers static
                                     SPA
  ---------------------------------------------------------------------

Treat versions and hosting capabilities as a baseline to verify, not
permission to upgrade dependencies or change hosting without checking
the repository and deployment plan.

## 3. Tenancy and identity

The latest recorded decision in the supplied changelog and known-issues
document is **Design A: one institution per institution user**.

-   `users.account_type = platform` and `users.institution_id = NULL`
    for platform users.
-   `users.account_type = institution` and `users.institution_id`
    references exactly one institution for institution users.
-   Institution users may access only records belonging to their
    institution.
-   Platform operations may have explicitly authorised cross-institution
    visibility.
-   Organization-unit scope is an additional authorization boundary
    within an institution; it does not replace tenant isolation.
-   Email is recorded as globally unique in the current schema.

**Do not revive the retired global-user/membership-pivot Design B**
(`institution_user`, active-institution switching) without a new
explicit architecture decision. The old `InstitutionContext` and
phase-2.5 design material were written for that alternative and must not
be wired into the live application merely because files remain in the
repository.

Before changing tenancy code, verify the actual migrations, models,
middleware and every affected query. Do not infer that a class or table
exists from an old design document.

## 4. Backend architecture

Organize the Laravel application as a modular monolith. Each domain
module owns its routes/controllers, validation, services/policies,
resources and tests as appropriate.

Expected domain areas include:

-   Platform operations and institution provisioning
-   Institution profile and organization structure
-   Identity, users, roles and access management
-   Academic core
-   Admissions
-   Student records
-   Finance
-   Timetable and attendance
-   Examinations and grading
-   Shared audit, notifications and file-storage capabilities

Rules:

1.  Institution-scoped reads and writes must derive the institution from
    authenticated server-side context, not trust a client-supplied
    `institution_id`.
2.  Validate that related records (for example, a user assignment and
    its organization unit) belong to the same institution.
3.  Centralize authorization decisions. Do not create parallel,
    contradictory permission pipelines.
4.  Keep controllers thin; put reusable domain rules in services and
    policies.
5.  Use transactions for multi-record workflows such as institution
    provisioning, admission decisions and result publication.
6.  Preserve API response contracts. Use API Resources/DTOs when
    response complexity warrants them.
7.  Use additive migrations for deployed databases. Never use
    `migrate:fresh` or `migrate:refresh` against a database containing
    data.
8.  Avoid cross-module controller joins and direct manipulation of
    another module's tables. Use a clear module service boundary.

## 5. Authorization model

The supplied documents report platform/institution account-type
middleware, role assignments, permission catalogue work and audit
functionality at different stages. Their implementation status is not
consistently described.

Therefore:

-   Treat backend middleware, policies and services as the security
    boundary.
-   Verify the actual permission catalogue, role-permission mapping,
    scoped assignment rules and audit writes in code before extending
    them.
-   Do not rely on navigation metadata or hidden buttons as
    authorization.
-   Every privileged mutation must have a server-side authorization
    check and an appropriate audit event.
-   Scope checks must consider institution and, where applicable,
    organization unit and record ownership.
-   Keep MFA enforcement for privileged actions aligned with the actual
    production configuration and the quality/operations release gate.

## 6. Web architecture

The web app is a React SPA organized by workspace and feature module.

-   Workspaces represent user experiences (for example, Platform
    Administration and Institution Administration), not individual
    roles.
-   Roles and permissions determine which areas, pages and actions a
    user may access.
-   Shared layout and design-system components belong in shared
    foundations.
-   Feature-specific pages, API functions, types and styles belong in
    their module.
-   Use the shared API client and service layer; do not introduce ad hoc
    network calls inside page components.
-   Reuse common tables, forms, cards, status indicators, filters,
    pagination and CRUD patterns.
-   Avoid page-specific copies of shared styles or duplicated CRUD
    implementations.
-   Keep CSS scoped and consistent with the existing design tokens and
    design system.

## 7. Admissions frontend architecture

Admissions has three distinct user-facing areas. Keep their
responsibilities separate while sharing the design system and API
client:

-   **`modules/public-portal`**: institution-branded admission landing,
    public application tracking, and public credential verification.
-   **`modules/applicant-portal`**: applicant registration, email
    verification, password setup, login, authenticated workspace,
    profile and application lifecycle.
-   **`modules/admissions`**: institution-side intake, application
    review, decision and enrolment administration.

The central router owns route composition. Feature modules own their
pages, types, API functions and feature-specific styles. Do not maintain
parallel implementations of the same applicant journey in multiple
modules.

The account-based application rule is authoritative: applicants must
register, verify their email, set a password and authenticate before
creating, editing or submitting an application. Anonymous users must not
create, edit or submit applications.

The public tracking journey is a separate, read-only capability. It
accepts an application number and date of birth through a POST-based API
operation; date of birth must not be placed in a URL or query string.
The server must rate-limit requests, scope the lookup to the institution
identified by the route, return only a deliberately limited status
response and use non-enumerating error behaviour. Do not expose
applicant profile fields, documents, internal review notes or decision
metadata that are not approved for public disclosure.

The institution-branded landing page is public and
institution-contextual. It should provide clear entry points to
applicant registration, applicant login and public application tracking.
Institution context must be resolved from a validated institution code
and must not be trusted as authorization for private data.

### Embedded portal boundary

The iframe resize/parent bridge is an application-level integration
capability, not applicant-authentication business logic. Keep its
implementation in an application-level integration/shared location (or
retain its current location temporarily if moving it is outside the
approved change). It must:

-   validate the embedding/referrer origin against the configured
    allow-list;
-   validate message origin and source on both sides of the bridge;
-   use a narrow, documented message contract for resize/navigation
    needs;
-   avoid wildcard `postMessage` targets;
-   avoid sending applicant data, tokens or credentials to the parent
    page;
-   preserve internal React Router navigation inside the embedded
    application;
-   avoid full-page navigation/reload for ordinary in-app route changes.

Do not move or delete the existing bridge until all imports, bootstrap
wiring, Laravel wrapper validation and deployed behaviour have been
inspected and regression-tested.

### Target route responsibilities

The following is the intended route responsibility, not a statement that
every route is currently implemented or wired:

  -----------------------------------------------------------------------------------
  Route pattern                                   Responsibility
  ----------------------------------------------- -----------------------------------
  `/apply/:institutionCode`                       Institution-branded public
                                                  admission landing

  `/apply/:institutionCode/register`              Applicant account registration

  `/apply/:institutionCode/login`                 Applicant login

  `/apply/:institutionCode/verify-email`          Email verification continuation

  `/apply/:institutionCode/set-password`          Password setup/activation

  `/apply/:institutionCode/resend-verification`   Verification resend

  `/apply/:institutionCode/status`                Public read-only application
                                                  tracking

  `/applicant/*`                                  Authenticated applicant workspace

  `/verify/:credentialNumber`                     Public credential verification
  -----------------------------------------------------------------------------------

Route guards improve navigation UX only. Every private operation remains
protected by backend applicant authentication, institution scoping,
ownership checks and authorization.

## 8. Mobile architecture

The Expo application is documented as a scaffold without product
functionality. Mobile is deferred from the MVP unless the owner
explicitly changes scope. Do not spend time implementing mobile features
while the agreed web/API pilot path remains incomplete.

When mobile work is authorised, use the Expo SDK version actually
installed in the repository, consult its matching official
documentation, and use `npx expo install` for native dependencies.

## 9. Data and integration boundaries

-   `institution_id` is the tenant key for institution-owned records
    unless a documented domain exception is approved.
-   Use stable business codes/slugs for external references where
    appropriate; do not expose internal database IDs as durable
    integration contracts without a reason.
-   File access must be institution-scoped and authenticated; sensitive
    documents must not be exposed through predictable public paths.
-   Notifications should be a shared capability rather than separate ad
    hoc implementations in every module.
-   Audit events should use consistent, module-qualified event names and
    exclude secrets and unnecessary sensitive payloads.
-   External integrations must be isolated behind adapters/services; do
    not couple core workflows directly to one vendor.

## 10. Deployment topology

The supplied documentation describes a static web application on
Cloudflare and a Laravel API on MilesWeb, with MySQL on the hosting
plan. Verify current domains, environment variables, CORS, PHP version,
document root, cron/SSH availability and backup capabilities before each
deployment.

Never put API secrets, database credentials, signing keys or private
keys in frontend `VITE_*` or `EXPO_PUBLIC_*` variables. Those values are
public in built client applications.

## 11. Architecture decision process

Record significant decisions in this document or a dated decision record
with:

-   Context/problem
-   Options considered
-   Decision and rationale
-   Consequences/trade-offs
-   Date and owner
-   Code/docs/migrations affected

Do not leave mutually exclusive designs described as simultaneously
active. Superseded designs should be labelled historical and must not be
used as implementation instructions.
