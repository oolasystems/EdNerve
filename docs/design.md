# OolaEdNerve --- Product and Solution Design

**Status:** consolidated working design. Business rules and
pilot-specific regulations require confirmation with the institution
before implementation.

## 1. Product definition

OolaEdNerve is Oola Systems' modular Higher Education ERP for
universities and colleges. It aims to provide an affordable,
maintainable platform covering institutional administration and the
major academic and administrative lifecycles.

The product is delivered as one tenant per institution. Each institution
has its own users, organization hierarchy, academic structure and
operational records.

## 2. Workspaces and personas

Workspaces are distinct user experiences. Multiple roles may use the
same workspace; RBAC controls access within it.

  -----------------------------------------------------------------------
  Workspace               Primary users           Intended responsibility
  ----------------------- ----------------------- -----------------------
  Platform Operations     Oola Systems platform   Provision and manage
                          administrators          institutions; platform
                                                  health and
                                                  administration

  Institution             Institution             Institution
  Administration          administrators and      configuration and
                          authorised staff        administration of ERP
                                                  modules

  Academic Staff          Faculty and authorised  Teaching workload,
                          academic staff          timetable, attendance,
                                                  academic records and
                                                  permitted marks
                                                  workflows

  Applicant Portal        Applicants              Public application and
                                                  application-status
                                                  journeys

  Student Portal          Students                Personal academic,
                                                  attendance, timetable,
                                                  result and fee
                                                  information

  Parent/Guardian         Parent or guardian      Restricted, consent-
                                                  and
                                                  institution-governed
                                                  student information;
                                                  evaluate after MVP
  -----------------------------------------------------------------------

Do not create a separate layout for every role. Build shared workspace
shells and use backend authorization for capabilities.

## 3. Product principles

1.  Institution isolation is mandatory.
2.  Academic and administrative workflows must be auditable.
3.  A workflow should have explicit states, permitted transitions, actor
    responsibilities and validation rules.
4.  Sensitive operations require separation of duties where the domain
    demands it.
5.  Institution-specific regulations must be configurable without
    embedding one institution's rules as universal defaults.
6.  Build an end-to-end usable vertical slice before expanding module
    breadth.
7.  Reuse existing verified functionality; do not rebuild working
    modules without evidence of a defect or approved redesign.

## 4. Module catalogue and indicative scope

  -----------------------------------------------------------------------
  Domain                  Main responsibilities   Delivery direction
  ----------------------- ----------------------- -----------------------
  Platform administration Institution             Core platform
                          provisioning, status,   
                          platform users and      
                          operational oversight   

  Institution             Institution profile,    Core platform
  administration          organization units,     
                          users, roles and        
                          configuration           

  Academic management     Academic sessions,      Core academic spine
                          programmes,             
                          courses/subjects,       
                          syllabus, sections,     
                          course registration,    
                          calendar and workload   

  Admissions              Intake, applications,   Pilot-critical; confirm
                          eligibility, review,    institution rules
                          merit/counselling,      
                          offers and enrolment    

  Student management      Student profile,        Core ERP
                          enrolment, academic     
                          history, documents and  
                          lifecycle               

  Finance                 Fee heads/structures,   Core ERP;
                          allocation, student     accounting/gateway may
                          ledger, payments and    be later
                          receipts                

  Timetable and           Timetables, class       Core ERP
  attendance              registers, attendance   
                          summaries and exam      
                          eligibility inputs      

  Examination and grading Examination             Core ERP; stage by
                          setup/scheduling,       registrar workflow
                          question bank/papers,   
                          duty roster, marks,     
                          results and revaluation 

  Faculty/HR              Faculty profiles,       Stage after core
                          workload and later      academic workflows
                          employee                
                          lifecycle/payroll       

  Shared services         Audit, notifications,   Cross-cutting,
                          file storage, reports   introduced when
                          and integration support required

  Public website CMS      Institution-managed     Deferred/descoped
                          public content          unless pilot requires

  Mobile                  Native mobile           Deferred unless pilot
                          experience              requires
  -----------------------------------------------------------------------

This catalogue is a product direction, not a claim that every module is
complete or in the MVP.

## 5. Core domain relationships

The intended lifecycle includes:

-   Institution → organization units → programmes and academic
    structures.
-   Applicant → application → admission decision → enrolment → student
    profile.
-   Student → programme/session/section → course registrations.
-   Academic session + programme/course/section → timetable and
    attendance.
-   Examination + eligible student/course → assessment, marks, result
    and publication.
-   Student + fee structure/allocation → ledger → payment/receipt.

The exact schema and lifecycle states must follow the current migrations
and approved feature rules. Do not create duplicate entities where an
existing model already represents the concept. In particular, the
supplied changelog reports `academic_sections` as the first-class
sections/batches entity; the older admissions design's proposed
`batches`/`divisions` tables must be reconciled with that model before
implementation.

## 6. Workflow design standard

Every substantial workflow specification must document:

1.  Purpose and actors
2.  Preconditions and required permissions
3.  States and allowed transitions
4.  Validation and business rules
5.  Data created or updated
6.  API operations and response/error behaviour
7.  UI screens and reusable components
8.  Audit events and sensitive-data handling
9.  Notifications and integrations, if any
10. Acceptance tests, including negative and cross-tenant cases

### Admissions example --- design questions before extending

The supplied Admissions Engine design covers eligibility, entrance
exams, merit rules, reservations, seat allocation and public portal
behaviour. Before coding these items:

-   Obtain the pilot institution's approved admission regulations and
    category/quota rules.
-   Confirm whether merit is calculated, imported or manually approved
    for each intake.
-   Define tie-breaking, rounding, document verification, waitlist and
    withdrawal rules.
-   Define who may override eligibility or merit and how the override is
    audited.
-   Reuse `academic_sections` and existing programme/session entities
    where they represent the same concepts.
-   Specify idempotency and concurrency protection for seat allocation.

Do not assume a Maharashtra-flavoured example or one institution's quota
rules apply to every tenant.

### Academic and examination example --- design questions before extending

Use the academic session/programme/course structure as the spine. For
examination workflows, define the authority and separation-of-duties
sequence for paper preparation, checking, approval, ratification,
scheduling, duty allocation, marks entry, result approval/publication
and revaluation. Treat F8 answer-script digitisation as deferred unless
separately approved. Verify the deployed status of locally implemented
examination phases before planning additional work.

## 7. Admissions applicant experience --- approved product decisions

The following decisions are approved product direction and supersede
conflicting descriptions elsewhere in older project notes:

1.  **Application submission is account-based only.** An applicant must
    register, verify the email address, set a password and sign in
    before creating, editing or submitting an application.
2.  **The public landing page is institution-branded.** Each
    institution's admission entry page presents institution identity and
    the available admission entry points.
3.  **Application tracking is publicly available by application number
    and date of birth.** This is a read-only lookup; it does not grant
    access to the applicant workspace or permit changes to an
    application.

These are target product rules. They do not assert that all related
pages, routes, API endpoints or security controls have already been
implemented.

### 9.1 Applicant journey

The target journey is:

1.  Applicant opens `/apply/:institutionCode`.
2.  The institution-branded landing page explains the admission process
    and links to registration, login and application tracking.
3.  A new applicant registers using the institution's applicant entry.
4.  The applicant receives an email verification flow.
5.  The applicant verifies the email and completes password setup.
6.  The account is activated and the applicant is directed to login or
    authenticated continuation, according to the implemented token flow.
7.  The applicant signs in and enters the applicant workspace.
8.  The applicant completes profile information and creates an
    application for an eligible intake/programme.
9.  The applicant saves a draft, reviews it and submits it explicitly.
10. The applicant can return to the authenticated workspace to view the
    application and any permitted follow-up actions.

The exact account activation token sequence, expiry, resend limits and
session behaviour must match the verified backend contract. Do not
invent a second password email if the backend's actual flow supplies a
password setup token through the verification response.

### 9.2 Public institution landing page

The landing page is public but institution-specific. It should:

-   display verified institution branding and admission information;
-   show currently available intakes/programmes only when the API
    confirms they are open and eligible for public display;
-   provide clear registration and login actions;
-   provide a separate public application-tracking entry;
-   explain the distinction between applicant account access and public
    tracking;
-   present accessible loading, empty, unavailable and error states.

Do not make a public landing page a substitute for server-side intake
eligibility or application validation.

### 9.3 Authenticated applicant workspace

The applicant workspace is private and must enforce ownership on the
backend. Its target capabilities are:

-   applicant dashboard and application status summary;
-   applicant profile and contact details;
-   create and save an application draft;
-   edit an application only while its server-defined state permits;
-   review the complete application before submission;
-   submit with explicit confirmation and clear acknowledgement;
-   view submitted application details and permitted status history;
-   respond to institution requests only where a defined workflow
    permits.

A user must not be able to access another applicant's records by
changing an application ID, applicant ID or route parameter. The backend
must derive the applicant identity from the authenticated applicant
session/token.

### 9.4 Public application tracking

The public tracking form collects an application number and date of
birth. It must submit these values in a POST request, not in a URL,
query string, browser route, analytics event or client log.

Security and privacy requirements:

-   apply rate limits and abuse monitoring;
-   scope lookup to the institution code in the route and verify the
    application belongs to that institution;
-   use generic failure responses that do not reveal whether an
    application number or date of birth was correct independently;
-   return only approved public fields, such as application reference,
    programme/intake label and a high-level status, subject to
    institution policy;
-   never return uploaded documents, private contact information, date
    of birth, internal notes, reviewer identity or sensitive decision
    details;
-   prevent caching of sensitive lookup responses where appropriate;
-   avoid storing submitted date of birth in application logs or
    telemetry;
-   define privacy notice, retention and disclosure requirements with
    the pilot institution before release.

The tracking lookup is not a second applicant login mechanism and must
not provide edit, upload, withdrawal or submission actions.

### 9.5 Separate public credential verification

Credential verification at `/verify/:credentialNumber` remains a
separate public service. It must not be conflated with admission
application tracking. Its response should disclose only the fields
approved for public credential validation and should use its own
verification rules and audit/abuse controls.

### 9.6 Retiring the legacy anonymous submission path

The older anonymous `PublicApplyPage` behaviour is not part of the
approved active product journey. Do not route users to anonymous
application creation or submission.

Before removing legacy code or API endpoints:

-   inspect references, tests, external links and production usage;
-   identify reusable fields and validation rules for the authenticated
    application editor;
-   preserve any required compatibility until a controlled cutover is
    approved;
-   remove or disable the anonymous mutation path only after the
    account-based path is implemented and regression-tested.

The old public status page may provide reusable UI/business-rule
reference, but the active public tracking route must implement the
application-number plus date-of-birth lookup contract.

## 8. Admissions workflow acceptance criteria

The account-based applicant slice is acceptable only when all relevant
criteria pass:

-   Institution context is validated and remains consistent through the
    journey, including when embedded in the Oola Systems wrapper.
-   Registration, email verification, password setup and login work
    against the actual Laravel API contract.
-   Applicant authentication is distinct from institution-user
    authentication and cannot grant institution administration access.
-   Applicant records and applications are isolated by institution and
    applicant ownership on the server.
-   Draft creation, editing and submission follow explicit server-side
    state transitions.
-   Public tracking uses application number plus date of birth via POST,
    rate limits attempts and returns only approved read-only
    information.
-   Public credential verification remains independent.
-   Iframe embedding, origin validation, resize messaging and internal
    navigation continue to work.
-   Tests cover invalid institution codes, invalid/expired tokens,
    duplicate registration, unauthenticated mutations, cross-applicant
    access, cross-institution access, tracking mismatch, throttling and
    forbidden public fields.

## 9. MVP and non-goals

The current documents contain different MVP definitions and progress
estimates. The working delivery principle is:

-   Stabilise and verify the deployed platform and institution
    administration path.
-   Select one pilot institution and one or two end-to-end ERP workflows
    that prove real operational value.
-   Complete the academic/admissions/examination sequence according to
    pilot priority and dependencies.
-   Defer broad peripheral modules, full accounting, payment gateway,
    SSO, AI, i18n, mobile and CMS unless a pilot requirement is
    explicitly approved.

The exact pilot institution, MVP acceptance boundary, date and module
priorities must be recorded by the owner in `plan.md`.

## 10. Acceptance standard

A feature is accepted only when:

-   Business rules are approved and documented.
-   API validation and authorization are enforced server-side.
-   Tenant and organization-unit boundaries are tested.
-   UI uses shared components and handles loading, empty, error and
    success states.
-   Audit events are recorded for material mutations.
-   Automated tests pass and relevant manual UAT is recorded.
-   Deployment and rollback implications are understood.
-   Documentation and checklist are updated.

