# OolaEdNerve --- Development Checklist & Status

**Status date:** 26 November 2026\
**Purpose:** Single live development-status checklist for OolaEdNerve.\
**Status evidence rule:** `[P]` = production verified; `[L]` = locally
implemented and verified; `[~]` = partially delivered; `[ ]` = pending;
`[?]` = requires verification; `[D]` = deferred by explicit scope
decision.

> **Important:** A local test pass is not evidence of production
> deployment. Percentages are approximate estimates of the listed module
> scope delivered, not audited KPIs. They do not imply production
> readiness. Historical status statements are retained only where
> consistent with the latest reported development evidence.

## 1. Executive status

  -------------------------------------------------------------------------------
               Sr. No. Module / Feature        Status (%age) Remark
  -------------------- ---------------- -------------------- --------------------
                     1 Platform and            90% `[P]/[?]` Core platform and
                       institution                           institution
                       administration                        workspaces, tenancy,
                                                             RBAC and audit
                                                             capabilities have
                                                             previously been
                                                             reported live.
                                                             Reconfirm current
                                                             production state
                                                             against repository
                                                             and target
                                                             environment.

                     2 Security and                75% `[~]` Security controls
                       operations                            and documentation
                                                             substantially built;
                                                             production
                                                             credential rotation,
                                                             privileged MFA
                                                             enrolment and
                                                             backup-restore drill
                                                             remain open.

                     3 Academic core           90% `[L]/[P]` Academic sessions,
                                                             programmes,
                                                             subjects, hierarchy,
                                                             syllabi and related
                                                             academic
                                                             enhancements
                                                             delivered to varying
                                                             environments;
                                                             several recent
                                                             enhancements remain
                                                             locally verified
                                                             only.

                     4 Question bank               75% `[~]` Question bank, paper
                       and paper                             authoring, templates
                       setting                               and approval
                                                             workflow reported
                                                             live.
                                                             Confidential-paper
                                                             access restriction
                                                             and ratified-paper
                                                             print watermark
                                                             remain incomplete.

                     5 Examination and             45% `[~]` Core examinations,
                       grading                               marks/results, hall
                                                             tickets and
                                                             revaluation reported
                                                             live. F4/F5
                                                             enhancements are
                                                             locally built and
                                                             require production
                                                             deployment
                                                             verification; F6 and
                                                             moderation remain
                                                             open.

                     6 Admissions                  45% `[~]` Intake, public
                                                             application, status
                                                             tracking and
                                                             application decision
                                                             pipeline reported
                                                             live. Eligibility,
                                                             counselling, quota,
                                                             document
                                                             verification and
                                                             advanced admission
                                                             controls remain
                                                             open.

                     7 Student records             40% `[~]` Student profiles,
                                                             enrolment/session
                                                             association and
                                                             result visibility
                                                             available. CGPA,
                                                             document vault,
                                                             certificates and
                                                             student self-service
                                                             remain open.

                     8 Finance                     55% `[~]` Fee
                                                             heads/structures,
                                                             allocation, ledger,
                                                             manual payments and
                                                             receipts available.
                                                             Accounting, budgets,
                                                             procurement,
                                                             inventory and GST
                                                             remain outside
                                                             current scope.

                     9 Timetable and               85% `[~]` Timetable, class
                       attendance                            registers,
                                                             attendance summaries
                                                             and exam-eligibility
                                                             integration
                                                             delivered.
                                                             Holiday-aware
                                                             timetable behaviour
                                                             needs verification.

                    10 Notifications                0% `[ ]` Shared email/in-app
                       and shared                            notification service
                       services                              is not yet
                                                             implemented; F5
                                                             notification
                                                             integration remains
                                                             open.

                    11 Reports and MIS              0% `[ ]` Report registry,
                                                             exports and
                                                             institutional MIS
                                                             packs not
                                                             implemented.

                    12 Mobile                       5% `[D]` Expo scaffold only;
                       application                           native mobile
                                                             product
                                                             functionality
                                                             deferred.

                    13 Student and                  0% `[D]` Deferred beyond the
                       parent portals                        approved pilot
                                                             slice.

                    14 HR, library,                 0% `[D]` Deferred from the
                       hostel,                               current pilot scope.
                       transport and                         
                       inventory                             

                    15 Full envisioned       Approx. 45--55% Directional estimate
                       product                               only; includes
                                                             modules
                                                             intentionally
                                                             deferred from the
                                                             pilot.
  -------------------------------------------------------------------------------

## 2. Phase 0 --- Baseline, source control and release hygiene

  ------------------------------------------------------------------------------------
               Sr. No. Module / Feature               Status (%age) Remark
  -------------------- ----------------------- -------------------- ------------------
                     1 Git repository, remote,             0% `[ ]` Confirm
                       branch and commit                            repository, active
                       history                                      branch, remote,
                                                                    commit history and
                                                                    working-tree
                                                                    status.

                     2 Source backup and                   0% `[ ]` Commit or safely
                       restore/clone                                back up intended
                       verification                                 changes; verify a
                                                                    clean
                                                                    clone/restore.

                     3 Repository secret and               0% `[ ]` Check tracked
                       generated-file audit                         `.env`,
                                                                    credentials,
                                                                    database dumps,
                                                                    logs, generated
                                                                    bundles and
                                                                    scratch archives.

                     4 `.gitignore` and                    0% `[ ]` Verify repository
                       `.gitattributes`                             and
                                                                    subproject-level
                                                                    rules.

                     5 Runtime/toolchain                   0% `[ ]` Record PHP,
                       inventory                                    Composer, Node,
                                                                    npm, Laravel,
                                                                    React, Vite, Expo
                                                                    and database
                                                                    versions.

                     6 Clean-checkout API                  0% `[ ]` Run tests, lint
                       tests and web build                          and production
                                                                    build from a clean
                                                                    checkout.

                     7 Production URL, CORS                0% `[?]` Confirm actual
                       and environment                              API/web URLs and
                       verification                                 build-time
                                                                    environment
                                                                    variables.

                     8 Production                          0% `[?]` Compare production
                       schema/migration                             schema with
                       reconciliation                               repository
                                                                    migrations and
                                                                    record pending
                                                                    migrations.

                     9 Documentation-to-code               0% `[ ]` Reconcile routes,
                       reconciliation                               models,
                                                                    middleware,
                                                                    services and UI
                                                                    with current
                                                                    documentation.

                    10 Safe migration/seed                 0% `[ ]` Confirm no
                       procedure                                    destructive
                                                                    migration or seed
                                                                    command can run
                                                                    against valuable
                                                                    data.
  ------------------------------------------------------------------------------------

## 3. Security and operations --- P0 / pilot blockers

  ------------------------------------------------------------------------------------
               Sr. No. Module / Feature         Status (%age) Remark
  -------------------- ----------------- -------------------- ------------------------
                     1 Production                   70% `[~]` Code defaults were
                       password hygiene                       hardened; rotate any
                                                              existing production/demo
                                                              accounts that may still
                                                              use known passwords.

                     2 Demo-account                  0% `[ ]` Disable/remove demo
                       removal                                `*.ednerve.test`
                                                              accounts from pilot
                                                              production.

                     3 Privileged-user              70% `[~]` MFA capability and
                       MFA enrolment                          rollout instructions
                                                              exist; confirm all
                                                              privileged production
                                                              users are enrolled and
                                                              recovery is tested.

                     4 MFA gates on                 80% `[~]` Verify each
                       sensitive                              pilot-sensitive action
                       operations                             and its enforcement in
                                                              the target environment.

                     5 Production                    0% `[?]` Verify
                       configuration and                      `APP_ENV=production`,
                       secrets                                `APP_DEBUG=false`,
                                                              production secrets and
                                                              least-privilege DB
                                                              credentials.

                     6 Web/mobile build              0% `[?]` Verify no secret is
                       secret hygiene                         embedded in client build
                                                              variables or repository
                                                              history.

                     7 Audit coverage               80% `[~]` Audit infrastructure and
                                                              several workflows exist;
                                                              verify coverage for
                                                              institution/user/role,
                                                              admissions, exams, marks
                                                              and results.

                     8 Sensitive-log                 0% `[ ]` Verify logs exclude
                       minimisation                           passwords, tokens, keys
                                                              and unnecessary student
                                                              data.

                     9 Database backup               0% `[ ]` Execute a backup and
                       and restore drill                      restore to a separate
                                                              scratch database; record
                                                              evidence.

                    10 Incident response             0% `[ ]` Verify contact, evidence
                                                              preservation and
                                                              escalation procedure;
                                                              conduct rehearsal.

                    11 Production health             0% `[?]` Verify health endpoint
                       and smoke test                         and smoke test using a
                                                              dedicated low-privilege
                                                              account.

                    12 Security                    100% `[L]` Security, backup,
                       documentation                          incident-response,
                                                              key-rotation and
                                                              compliance documentation
                                                              reported prepared;
                                                              operational execution
                                                              remains open.
  ------------------------------------------------------------------------------------

## 4. Architecture and shared foundations

  -------------------------------------------------------------------------------------
               Sr. No. Module / Feature            Status (%age) Remark
  -------------------- -------------------- -------------------- ----------------------
                     1 Tenant model --- one       100% `[L]/[P]` Accepted architecture:
                       institution per                           one university/college
                       tenant                                    is one tenant;
                                                                 organization units are
                                                                 divisions within that
                                                                 institution.

                     2 Institution-scoped              90% `[~]` Tenant isolation is a
                       data access                               core implemented rule.
                                                                 Continue verifying all
                                                                 institution-owned
                                                                 reads/writes and
                                                                 cross-tenant
                                                                 relationships.

                     3 Organization-unit               90% `[~]` Verify
                       boundaries                                organization-unit
                                                                 relationships cannot
                                                                 cross institution
                                                                 boundaries.

                     4 Legacy                           0% `[ ]` Identify dead legacy
                       tenancy/design                            design code; retire
                       cleanup                                   only after reference
                                                                 and test review.

                     5 Authoritative                   80% `[~]` Verify one consistent
                       authorization                             authorization pipeline
                       pipeline                                  and safely remove
                                                                 contradictory/unused
                                                                 mechanisms.

                     6 RBAC and permission             90% `[~]` Core RBAC/effective
                       catalogue                                 permissions reported
                                                                 implemented; reconcile
                                                                 permission catalogue
                                                                 and scoped permissions
                                                                 with actual
                                                                 routes/schema.

                     7 Audit model, service            90% `[~]` Audit/hash-chain
                       and event taxonomy                        capability exists;
                                                                 verify event taxonomy
                                                                 and coverage against
                                                                 current
                                                                 implementation.

                     8 API contracts,                  80% `[~]` Reconcile
                       validation and                            request/response
                       pagination                                contracts, error
                                                                 handling and
                                                                 pagination
                                                                 conventions.

                     9 Shared web design               80% `[~]` Continue enforcing
                       system                                    reusable layout, CRUD,
                                                                 form, table, filter
                                                                 and pagination
                                                                 components; avoid
                                                                 page-specific
                                                                 duplication.
  -------------------------------------------------------------------------------------

## 5. Platform and institution administration

  -------------------------------------------------------------------------------------------------
               Sr. No. Module / Feature                     Status (%age) Remark
  -------------------- ----------------------------- -------------------- -------------------------
                     1 Platform authentication and          90% `[P]/[?]` Login/logout, session
                       session lifecycle                                  restoration and
                                                                          account-type enforcement
                                                                          require current smoke
                                                                          verification.

                     2 Institution provisioning                100% `[P]` Institution provisioning
                                                                          and first-administrator
                                                                          workflow previously
                                                                          reported live; recheck
                                                                          transactional behaviour.

                     3 Institution                          90% `[P]/[?]` Verify
                       activation/deactivation                            activation/deactivation
                                                                          behaviour in current
                                                                          production environment.

                     4 Institution profile and                 100% `[P]` Core administration
                       organization units                                 capability previously
                                                                          reported live.

                     5 Institution user lifecycle           90% `[P]/[?]` Verify deactivation and
                                                                          token revocation
                                                                          behaviour.

                     6 Role assignment and scope            90% `[P]/[?]` Verify effective dates
                                                                          and organization-unit
                                                                          scope.

                     7 Platform/institution access          90% `[P]/[?]` Verify platform-admin and
                       boundaries                                         institution-admin access
                                                                          boundaries.

                     8 Workspace                                85% `[~]` Review all key screens
                       loading/empty/error/success                        for consistent state
                       states                                             handling.
  -------------------------------------------------------------------------------------------------

## 6. Academic core and student lifecycle

  ----------------------------------------------------------------------------------------------------
               Sr. No. Module / Feature              Status (%age) Remark
  -------------------- ---------------------- -------------------- -----------------------------------
                     1 Academic sessions and            100% `[P]` Core academic-session capability
                       status rules                                reported live; recheck status
                                                                   rules.

                     2 Faculties, schools and           100% `[P]` Three-tier academic hierarchy
                       academic units                              reported live.

                     3 Programmes, subjects             100% `[P]` Core catalogue and mappings
                       and programme-course                        reported live.
                       mapping                                     

                     4 Subject components and           100% `[P]` Theory/practical/tutorial
                       prerequisites                               components and prerequisite
                                                                   validation reported delivered.

                     5 Elective groups                  100% `[P]` Capacity, required choices and
                                                                   programme/semester associations
                                                                   reported delivered.

                     6 Syllabus versioning              100% `[P]` Draft/review/approve/publish,
                       and publication                             author/approver separation and
                                                                   immutable published versions
                                                                   reported live.

                     7 Course registration              100% `[L]` Per-session registration and
                                                                   elective enforcement built/tested
                                                                   locally; production deployment not
                                                                   confirmed.

                     8 Academic calendar                100% `[L]` Institution-scoped events and
                                                                   optional session linkage
                                                                   built/tested locally; production
                                                                   deployment not confirmed.

                     9 Academic                         100% `[L]` First-class `academic_sections`
                       sections/batches                            entity and CRUD built/tested
                                                                   locally; existing free-text
                                                                   timetable/exam section fields
                                                                   retained for compatibility.

                    10 Faculty workload                 100% `[L]` Per-staff teaching/load allocations
                       (lite)                                      and screen built/tested locally;
                                                                   production deployment not
                                                                   confirmed.

                    11 Lesson plans (lite)              100% `[L]` Unit-wise teaching plan/progress
                                                                   and screen built/tested locally;
                                                                   production deployment not
                                                                   confirmed.

                    12 Applicant-to-student              70% `[~]` Core
                       identity lifecycle                          application-to-decision/enrolment
                                                                   pipeline exists; verify end-to-end
                                                                   identity and edge cases.

                    13 Student profile and              100% `[P]` Student profile, programme/session
                       enrolment                                   association and lifecycle status
                                                                   reported live.

                    14 Student records audit             85% `[~]` Continue validating all mutations
                       and tenant scope                            and institution boundaries.

                    15 SGPA/CGPA aggregation              0% `[ ]` Not implemented.

                    16 Student document vault             0% `[ ]` TC, migration, photo and category
                                                                   document vault not implemented.

                    17 Student certificates               0% `[ ]` Bonafide, transfer and migration
                                                                   certificate workflows remain open.

                    18 Student/alumni                     0% `[D]` Deferred beyond current pilot
                       self-service                                scope.
  ----------------------------------------------------------------------------------------------------

## 7. Admissions

  ------------------------------------------------------------------------------------------------------------------
               Sr. No. Module / Feature                    Status (%age) Remark
  -------------------- ---------------------------- -------------------- -------------------------------------------
                     1 Programme-wise intakes and             100% `[P]` Core intake opening/closing and
                       application windows                               programme-wise capacity setup reported
                                                                         live; verify current rules.

                     2 Legacy public applicant           Historical 100% Earlier public submission and token-status
                       submission/status-by-token       `[P]` report --- behaviour was reported live. The approved
                       path                              **superseded as target is account-based submission plus
                                                     product direction** public application-number/date-of-birth
                                                                         tracking. Do not assume the old path has
                                                                         been removed from code or production.
                                                                         Verify usage and complete controlled
                                                                         cutover.

                     3 Application                            100% `[P]` Core pipeline reported live.
                       review/decision/enrolment                         
                       pipeline                                          

                     4 Programme-specific                       0% `[ ]` Marks, subjects, category and entrance
                       eligibility rules                                 cut-offs remain open.

                     5 Document upload and                      0% `[ ]` Verification/rejection/rework workflow not
                       verification                                      implemented.

                     6 Merit calculation and                    0% `[ ]` Define approved calculation, import/manual
                       tie-breaking                                      preparation and tie-breaking.

                     7 Reservation/quota and seat               0% `[ ]` Requires pilot regulations and approved
                       allocation                                        category/quota definitions.

                     8 Counselling and waitlist                 0% `[ ]` Counselling rounds, offers, acceptance,
                                                                         withdrawal and waitlist promotion remain
                                                                         open.

                     9 Admission overrides and                 50% `[~]` Existing decision audit needs verification;
                       audit                                             authorised override policy remains to be
                                                                         defined.

                    10 End-to-end                              50% `[~]` Add synthetic end-to-end tests for
                       applicant-to-enrolment tests                      eligibility, decisions and enrolment.

                    11 Application fee collection               0% `[D]` Manual-first approach; payment gateway
                                                                         deferred unless separately approved.

                    12 Bulk admission CSV import                0% `[ ]` Not implemented.

                    13 Applicant identity schema              100% `[L]` Canonical `admission_applicants` and
                       foundation                                        `admission_applicant_verification_tokens`
                                                                         tables, plus application linkage, are
                                                                         included in the revised create migrations;
                                                                         101 migrations and seeders executed
                                                                         successfully locally. This is schema
                                                                         evidence only, not proof of the complete
                                                                         account journey.

                    14 Institution-branded public               0% `[ ]` Approved target. Verify current page,
                       admission landing                                 institution branding/configuration, route
                                                                         and public entry actions before marking
                                                                         complete.

                    15 Applicant registration                   0% `[ ]` Approved account-based journey; verify API,
                                                                         duplicate handling, institution context,
                                                                         validation and tests.

                    16 Applicant email verification             0% `[ ]` Verify token generation, expiry, one-time
                       and resend                                        use, resend limits and email delivery.

                    17 Applicant password setup and             0% `[ ]` Verify actual backend token contract and
                       activation                                        activation state; do not rely on misleading
                                                                         UI copy.

                    18 Separate applicant                       0% `[ ]` Verify applicant login/session/logout and
                       authentication                                    strict separation from institution-user
                                                                         authentication.

                    19 Authenticated applicant                  0% `[ ]` Dashboard, profile, application
                       workspace                                         list/details and server-enforced applicant
                                                                         ownership.

                    20 Account-based application                0% `[ ]` Public anonymous submission is not the
                       draft/review/submission                           approved target. Verify authenticated draft
                                                                         creation, permitted editing, explicit
                                                                         submission and server-side state
                                                                         transitions.

                    21 Public application tracking              0% `[ ]` Approved read-only lookup. Verify POST
                       by application number + DOB                       contract, institution scoping, rate
                                                                         limiting, generic mismatch response and
                                                                         minimal public fields.

                    22 Public credential                        0% `[?]` Separate route/capability; inspect current
                       verification                                      route, API and deployed behaviour
                                                                         independently from application tracking.

                    23 Embedded applicant portal                0% `[?]` Verify Oola Systems iframe wrapper,
                       integration                                       allow-listed origin/source checks, resize
                                                                         messaging and internal SPA navigation after
                                                                         applicant route changes.

                    24 Anonymous submission                     0% `[ ]` Identify active routes/API usage and
                       legacy-path cutover                               external links. Disable/remove only after
                                                                         account-based flow is implemented,
                                                                         regression-tested and cutover is approved.
  ------------------------------------------------------------------------------------------------------------------

### Admissions status interpretation

The product decisions above change the required target journey, not the
historical evidence recorded in this checklist. The former 100% `[P]`
public-portal entry is retained as a historical report and labelled
superseded; it must not be treated as proof that the newly approved
account-based journey or application-number/date-of-birth tracking is
implemented. The new rows above remain open until repository tests and,
where applicable, production behaviour have been verified.

## 8. Finance

  ------------------------------------------------------------------------------------
               Sr. No. Module / Feature          Status (%age) Remark
  -------------------- ------------------ -------------------- -----------------------
                     1 Fee heads                    100% `[P]` Reported live.

                     2 Fee structures               100% `[P]` Course-wise/term-wise
                                                               structures reported
                                                               live.

                     3 Fee allocation               100% `[P]` Allocation engine
                                                               reported live.

                     4 Student fee ledger           100% `[P]` Term-wise dues/ledger
                                                               reported live.

                     5 Manual payments              100% `[P]` Manual payment and
                       and receipts                            receipt workflows
                                                               reported live.

                     6 Refunds and                    0% `[ ]` Define and verify rules
                       adjustments                             before pilot use.

                     7 Financial                     70% `[~]` Verify role
                       authorisation and                       restrictions and audit
                       audit                                   coverage for all
                                                               financial mutations.

                     8 Double-entry                   0% `[D]` Deferred to a later
                       accounting and                          tier.
                       chart of accounts                       

                     9 Budgets and                    0% `[D]` Deferred.
                       variance                                

                    10 Procurement and                0% `[D]` Deferred.
                       inventory/assets                        

                    11 GST invoice                    0% `[D]` Deferred.
                       handling                                

                    12 Payment gateway                0% `[D]` Deferred; requires
                                                               explicit approval.
  ------------------------------------------------------------------------------------

## 9. Timetable and attendance

  -----------------------------------------------------------------------------------------
               Sr. No. Module / Feature                Status (%age) Remark
  -------------------- ------------------------ -------------------- ----------------------
                     1 Timetable setup and grid           100% `[P]` Timetable grid
                                                                     reported live.

                     2 Faculty/own timetable              100% `[P]` Own timetable view
                       view                                          reported live.

                     3 Class register                     100% `[P]` Per-period
                                                                     present/absent/leave
                                                                     capability reported
                                                                     live.

                     4 Student attendance                 100% `[P]` Attendance summary
                       summary                                       reported live.

                     5 Faculty RBAC/own-class             100% `[P]` Own-class restrictions
                       restrictions                                  reported live.

                     6 Attendance threshold           100% `[L]/[P]` Windowed and
                       computation                                   per-subject threshold
                                                                     logic reported
                                                                     verified locally and
                                                                     integrated with exam
                                                                     eligibility; confirm
                                                                     target production
                                                                     state.

                     7 Exam eligibility gate          100% `[L]/[P]` Hall-ticket block and
                       and override                                  audited MFA override
                                                                     reported implemented;
                                                                     verify production
                                                                     configuration and
                                                                     audit.

                     8 Academic                             0% `[ ]` Holiday suppression
                       calendar/holiday-aware                        and calendar
                       timetable                                     interaction remain
                                                                     open.

                     9 Attendance correction               70% `[~]` Verify correction
                       audit                                         permissions and
                                                                     attributable audit
                                                                     records.
  -----------------------------------------------------------------------------------------

## 10. Question bank and paper setting

  ------------------------------------------------------------------------------------------------
               Sr. No. Module / Feature               Status (%age) Remark
  -------------------- ----------------------- -------------------- ------------------------------
                     1 Encrypted question bank           100% `[P]` Subject association, model
                                                                    answers and tags reported
                                                                    live.

                     2 Manual paper authoring            100% `[P]` Manual authoring and print
                                                                    capability reported live.

                     3 Blueprint generation              100% `[P]` Blueprint generation reported
                                                                    live.

                     4 Paper templates                   100% `[P]` Exam/unit-test/mid/sessional
                                                                    patterns reported live.

                     5 Paper                             100% `[P]` Draft → prepared → checked →
                       approval/ratification                        approved → ratified with
                       SoD                                          distinct actors and MFA at
                                                                    ratification reported live.

                     6 Approval trail and                100% `[P]` Workflow trail and UI reported
                       workflow UI                                  live.

                     7 Ratified-paper print               30% `[~]` Partial UI work only; complete
                       watermark                                    and verify watermark.

                     8 Confidential-paper                  0% `[ ]` Custodian-only pre-exam access
                       access restriction                           and audited access restriction
                                                                    not complete.

                     9 Institution-specific              100% `[P]` Configurable exam kinds
                       exam kinds                                   reported implemented; verify
                                                                    production data/configuration.
  ------------------------------------------------------------------------------------------------

## 11. Examination and grading --- F4/F5/F6

  ---------------------------------------------------------------------------------------------------------
               Sr. No. Module / Feature                       Status (%age) Remark
  -------------------- ------------------------------- -------------------- -------------------------------
                     1 Exams, kinds, schedules,                  100% `[P]` Core examination setup reported
                       subjects and components                              live.

                     2 Marks entry and result                    100% `[P]` Marks/results workflow reported
                       computation/publication                              live with SoD and MFA gates;
                                                                            verify current production
                                                                            behaviour.

                     3 Result history/versioning                 100% `[P]` ResultHistory versioning
                                                                            reported live.

                     4 Hall tickets                              100% `[P]` Hall-ticket print capability
                                                                            reported live; eligibility gate
                                                                            separately tracked.

                     5 Revaluation                               100% `[P]` Revaluation workflow reported
                                                                            live.

                     6 AMU ordinances and assessment         100% `[L]/[P]` Configurable grading bands,
                       schemes                                              pass criteria and assessment
                                                                            schemes built; LEGACY default
                                                                            retained, AMU-BTCSE opt-in.
                                                                            Confirm
                                                                            deployment/configuration before
                                                                            pilot use.

                     7 F4 exam scheduling                        100% `[L]` Centres, rooms, capacity,
                                                                            sessions, ranked conflict
                                                                            detection and attendance
                                                                            eligibility/override built and
                                                                            locally verified; production
                                                                            deployment/migration not
                                                                            confirmed.

                     8 F5.1 duty assignment                      100% `[L]` Staff acceptance/decline,
                       response/history                                     assignment replacement history,
                                                                            manager restrictions and
                                                                            auto-assignment corrections
                                                                            locally verified.

                     9 F5.2 cross-examination workload           100% `[L]` Workload includes eligible
                                                                            scheduled/published exams in
                                                                            same institution/session;
                                                                            excludes
                                                                            completed/different-session
                                                                            exams and declined/replaced
                                                                            duties; null session limited to
                                                                            target exam. Earlier baseline:
                                                                            93 tests, 498 assertions.

                    10 F5.3-A versioned roster data              100% `[L]` Institution-scoped roster
                       model                                                versions, assignment linkage
                                                                            and version-1 backfill
                                                                            implemented.

                    11 F5.3-B version-aware assignment           100% `[L]` Assignment create/update/delete
                       management                                           and auto-assignment restricted
                                                                            to latest draft; staff response
                                                                            restricted to latest published
                                                                            roster.

                    12 F5.3-C roster lifecycle                   100% `[L]` Draft → submitted → approved →
                                                                            published; rejection path;
                                                                            submitter/approver separation;
                                                                            published structural
                                                                            immutability.

                    13 F5.3-D audit and tenant                   100% `[L]` Lifecycle audit events, actor
                       isolation                                            attribution, hash-chain
                                                                            verification and
                                                                            cross-institution access tests
                                                                            verified.

                    14 F5.3-E version history and                100% `[L]` New version copies
                       workload consistency                                 assigned/accepted duties as new
                                                                            records; copied status resets
                                                                            to assigned and requires fresh
                                                                            response; declined/replaced
                                                                            remain in prior history;
                                                                            history metadata/counts and
                                                                            latest-version-only
                                                                            workload/auto-assignment
                                                                            verified.

                    15 F5.3-E targeted regression                100% `[L]` `ExamDutyTest`: **42 passed,
                       tests                                                419 assertions, 3.91 seconds**
                                                                            (reported 22 Nov 2026). Full
                                                                            project suite has not yet been
                                                                            reported after these latest
                                                                            changes.

                    16 F5 duty swap requests                       0% `[ ]` Not implemented; confirm pilot
                                                                            need and define
                                                                            request/approval/cancellation
                                                                            states.

                    17 F5 roster-approver SoD                      0% `[ ]` Remaining approval/publisher
                       refinement                                           policy and permission review.
                                                                            Do not assume existing
                                                                            lifecycle SoD covers every
                                                                            future role configuration.

                    18 F5 notification integration                 0% `[ ]` Depends on shared notification
                                                                            service; not implemented.

                    19 F5 production                               0% `[?]` Local implementation only.
                       deployment/migrations/seeds                          Review additive migrations,
                                                                            seed requirements, backup and
                                                                            deployment plan before
                                                                            production rollout.

                    20 F6 practicals and vivas                     0% `[ ]` Appointments, external letters,
                                                                            schedules, evaluations and
                                                                            discrepancy reporting not
                                                                            implemented.

                    21 Moderation/committee review                 0% `[ ]` Post-marking moderation remains
                                                                            open if required by pilot
                                                                            ordinances.

                    22 Supplementary/improvement                   0% `[ ]` Define from approved ordinances
                       attempt rules                                        before implementation.

                    23 Condonation/grace/max-backlog               0% `[ ]` Depends on approved ordinances.
                       rules                                                

                    24 F8 answer-script digitisation               0% `[D]` Deferred by scope decision; do
                                                                            not implement unless
                                                                            re-approved.

                    25 Digital marking on scanned                  0% `[D]` Dependent on F8; deferred.
                       scripts                                              

                    26 Examination calendar                        0% `[ ]` Deferred from initial F4 scope;
                       grid/planner UI                                      tabular scheduling view
                                                                            retained.
  ---------------------------------------------------------------------------------------------------------

## 12. Notifications, reports and other product surfaces

  -----------------------------------------------------------------------------------------------------
               Sr. No. Module / Feature                             Status (%age) Remark
  -------------------- ------------------------------------- -------------------- ---------------------
                     1 Shared notification service                       0% `[ ]` Email/in-app
                                                                                  notification service
                                                                                  not implemented.

                     2 F5 roster notifications                           0% `[ ]` Pending shared
                                                                                  notification service
                                                                                  and event
                                                                                  integration.

                     3 Reports and MIS registry                          0% `[ ]` Not implemented.

                     4 CSV/PDF report exports                            0% `[ ]` Not implemented as a
                                                                                  shared reporting
                                                                                  capability.

                     5 NAAC-style institutional packs                    0% `[ ]` Not implemented.

                     6 Alumni directory                                  0% `[D]` Deferred.

                     7 Public website CMS                               15% `[D]` Deprecated/descoped
                                                                                  in pilot plan.

                     8 Mobile app                                        5% `[D]` Expo scaffold only;
                                                                                  no product
                                                                                  functionality.

                     9 Student and parent portals                        0% `[D]` Deferred beyond
                                                                                  approved pilot scope.

                    10 HR and employee lifecycle                         0% `[D]` Deferred.

                    11 Library, hostel and transport                     0% `[D]` Deferred.

                    12 OpenAPI specification                             0% `[ ]` Not implemented.

                    13 SSO/SAML                                          0% `[D]` Deferred.

                    14 Internationalisation/multi-language               0% `[D]` Deferred.

                    15 AI services and broad analytics                   0% `[D]` Deferred.
  -----------------------------------------------------------------------------------------------------

## 13. Pilot release gate --- must close before pilot sign-off

  -------------------------------------------------------------------------------------
               Sr. No. Module / Feature              Status (%age) Remark
  -------------------- ---------------------- -------------------- --------------------
                     1 Pilot scope and                    0% `[ ]` Obtain explicit
                       acceptance criteria                         pilot institution
                                                                   sign-off.

                     2 Production                        50% `[~]` Code defaults
                       demo-credential                             hardened; production
                       rotation                                    account rotation
                                                                   remains operator
                                                                   action.

                     3 Privileged production             50% `[~]` Enrol privileged
                       MFA                                         users and test
                                                                   recovery.

                     4 Backup restore drill               0% `[ ]` Execute and document
                                                                   restore to scratch
                                                                   DB.

                     5 Production                         0% `[?]` Verify
                       environment/security                        configuration,
                       configuration                               secrets and DB
                                                                   privileges.

                     6 Critical                          80% `[~]` Local tests exist;
                       tenant-isolation and                        run complete current
                       authorization tests                         regression and
                                                                   verify deployed
                                                                   behaviour.

                     7 Full backend test                100% `[L]` Full local
                       suite                                       `php artisan test`
                                                                   passed: **137 tests,
                                                                   1,059 assertions,
                                                                   11.15 seconds** (25
                                                                   Nov 2026). This is
                                                                   local regression
                                                                   evidence, not
                                                                   production
                                                                   verification.

                     8 Web lint and                       0% `[?]` Run after current
                       production build                            frontend baseline is
                                                                   confirmed.

                     9 Migration review and              70% `[~]` Revised create-only
                       backup                                      migration set
                                                                   reviewed; **101
                                                                   migrations executed
                                                                   successfully with
                                                                   seeders locally**
                                                                   (25 Nov 2026).
                                                                   Production backup,
                                                                   production schema
                                                                   comparison and
                                                                   deployment review
                                                                   remain open.

                    10 F4/F5 production                   0% `[?]` Not confirmed;
                       deployment                                  schedule deployment
                                                                   only after migration
                                                                   and release review.

                    11 Production smoke tests             0% `[ ]` Execute after
                                                                   deployment using
                                                                   low-privilege and
                                                                   privileged test
                                                                   accounts as
                                                                   appropriate.

                    12 Pilot UAT                          0% `[ ]` Confirm UAT slot,
                                                                   execute scenarios,
                                                                   record defects and
                                                                   obtain sign-off.

                    13 Incident-response                  0% `[ ]` Conduct simulated
                       rehearsal                                   break-glass
                                                                   exercise.

                    14 DPDP notice and                    0% `[ ]` Add/verify notice
                       consent                                     and consent on
                                                                   public application
                                                                   form.

                    15 Privacy/log                        0% `[ ]` Verify PII
                       minimisation                                minimisation and
                                                                   retention in logs.

                    16 Rollback and operator              0% `[ ]` Verify rollback
                       support                                     procedure and
                                                                   support/operator
                                                                   documentation.

                    17 Final go/no-go                     0% `[ ]` Owner's explicit
                       approval                                    approval required;
                                                                   do not infer from
                                                                   test success.
  -------------------------------------------------------------------------------------

## 14. Deferred by default

  --------------------------------------------------------------------------------
               Sr. No. Module / Feature        Status (%age) Remark
  -------------------- ---------------- -------------------- ---------------------
                     1 Native mobile                0% `[D]` Deferred.
                       product                               
                       functionality                         

                     2 Public website              15% `[D]` Descoped/deprecated
                       CMS                                   in pilot plan.

                     3 Full                         0% `[D]` Deferred.
                       double-entry                          
                       accounting                            

                     4 Procurement and              0% `[D]` Deferred.
                       inventory                             

                     5 Payment gateway              0% `[D]` Deferred pending
                                                             explicit approval.

                     6 SSO/SAML and                 0% `[D]` Deferred.
                       external                              
                       identity                              
                       federation                            

                     7 Student/parent               0% `[D]` Deferred.
                       portals beyond                        
                       approved pilot                        
                       slice                                 

                     8 HR/payroll,                  0% `[D]` Deferred.
                       library, hostel                       
                       and transport                         

                     9 AI services,                 0% `[D]` Deferred.
                       broad                                 
                       analytics/MIS                         
                       and                                   
                       multi-language                        
                       support                               

                    10 Examination                  0% `[D]` Deferred by scope
                       answer-script                         decision.
                       digitisation                          
                       (F8)                                  
  --------------------------------------------------------------------------------

## 15. Current verified development evidence

> **Latest local baseline (25 November 2026):** 101 revised create-only
> migrations ran successfully with seeders; the full backend suite
> passed 137 tests and 1,059 assertions in 11.15 seconds. Neither result
> establishes production deployment or production readiness.

  -----------------------------------------------------------------------------
                Sr. No. Verification     Status           Remark
                        item                              
  --------------------- ---------------- ---------------- ---------------------
                      1 F5.3-E targeted  `[L]`            42 tests passed, 419
                        test suite                        assertions, 3.91
                                                          seconds; result
                                                          reported 22 Nov 2026.

                      2 F5.3-E           `[L]`            Assigned/accepted
                        roster-version                    assignments copied as
                        copy policy                       new records; copied
                                                          assignments reset to
                                                          assigned;
                                                          declined/replaced
                                                          assignments retained
                                                          in previous version.

                      3 F5.3-E roster    `[L]`            Version history and
                        history                           lifecycle
                                                          metadata/assignment
                                                          counts covered by
                                                          tests.

                      4 F5.3-E workload  `[L]`            Workload and
                        consistency                       auto-assignment use
                                                          latest roster version
                                                          for each eligible
                                                          examination.

                      5 F5.3-D lifecycle `[L]`            Lifecycle audit,
                        audit and tenant                  actor attribution,
                        isolation                         hash-chain and
                                                          cross-institution
                                                          checks previously
                                                          reported passing.

                      6 Full project     `[L]`            `php artisan test`:
                        backend suite                     **137 passed, 1,059
                                                          assertions, 11.15
                                                          seconds** (25 Nov
                                                          2026). Full local
                                                          backend regression
                                                          suite passed after
                                                          the revised migration
                                                          work.

                      7 Revised          `[L]`            **101 migrations
                        create-only                       executed successfully
                        migration set                     with seeders**
                        and seeders                       against the local
                                                          `ednerve_db` database
                                                          (25 Nov 2026).
                                                          Database-level
                                                          migration execution
                                                          succeeded locally.

                      8 Production       `[?]`            Not confirmed. Local
                        deployment of                     migration and test
                        F4/F5                             success must not be
                        enhancements and                  represented as
                        revised schema                    production
                                                          deployment.
  -----------------------------------------------------------------------------

## 16. Immediate next steps

1.  Commit this checklist update with the revised migration set and
    current backend verification evidence.
2.  Start Admissions Phase 3A with a contract/route audit, then build
    the approved account-based applicant journey in dependency order:
    shared design system, institution-branded landing, registration,
    email verification, password setup, applicant login, authenticated
    workspace, account-based application lifecycle and public
    application-number/date-of-birth tracking.
3.  Verify public credential verification separately and regression-test
    the Oola Systems iframe wrapper and embedded SPA navigation.
4.  Plan legacy anonymous-submission cutover only after the replacement
    path is verified; do not claim it is already removed.
5.  Run the web lint and production build when frontend files change.
6.  Compare the revised local schema with the production schema; prepare
    a non-destructive production migration and backup/rollback plan.
7.  Decide the exact pilot scope for the remaining F5 work: swap
    requests, roster-approver SoD and notifications.
8.  Plan production deployment only after backup, migration review,
    security gates and an approved deployment window.
9.  Close P0 pilot-readiness actions: production credential rotation,
    privileged MFA enrolment and backup restore drill.

**Status discipline:** Mark `[L]` only after local implementation and
tests have actually passed. Mark `[P]` only after the relevant
production behaviour has been verified. Keep `[?]` where the current
target environment has not been checked. Move a deferred item out of
`[D]` only through an explicit scope decision and update the project
plan.
