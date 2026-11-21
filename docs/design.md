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

## 7. MVP and non-goals

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

## 8. Acceptance standard

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
