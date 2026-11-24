# OolaEdNerve Backend Test Automation Report

**Project:** OolaEdNerve API\
**Backend location:** `C:\xampp\htdocs\EdNerve\ednerve-api`\
**Test framework:** Laravel PHPUnit feature tests\
**Primary example:** `tests/Feature/ExamDutyTest.php`\
**Purpose:** Document the automated backend testing approach, the Exam
Duty regression suite, the other feature-test areas, the execution
workflow, and the evidence available for future development.

------------------------------------------------------------------------

## 1. Executive Summary

OolaEdNerve uses Laravel automated tests to verify API behaviour,
database changes, authorization, institution isolation, and important
business rules. The main focus of this report is `ExamDutyTest.php`,
which grew from a small set of tests for duty-role seeding, manual
assignment, automatic assignment, and staff responses into a broader
regression suite for versioned duty rosters and their lifecycle.

The testing approach is API-oriented: tests create the required
institution, users, role assignments and academic/examination records;
authenticate users through Laravel Sanctum; call the API endpoints; and
assert HTTP responses, JSON payloads, database state, and audit
behaviour.

**Latest test results reported in the development conversation:**

-   Targeted `ExamDutyTest`: **51 passed, 615 assertions**.
-   Full backend suite: **132 passed, 1,021 assertions**.

These are reported local test results, not production test results.
Record a fresh run from the current working tree whenever the test suite
or related implementation changes.

## 2. Objectives of the Test Automation

The automated tests are intended to:

1.  Verify successful API workflows and expected response structures.
2.  Verify that invalid transitions and prohibited operations are
    rejected.
3.  Verify role-based access control and privileged MFA gates.
4.  Verify institution-level data isolation.
5.  Verify database persistence, history preservation, and compatibility
    projections.
6.  Verify workload and automatic-assignment business rules.
7.  Verify audit events, actor attribution, and audit-chain integrity
    where applicable.
8.  Protect existing features through repeatable regression tests when
    new functionality is added.

The tests are not a substitute for production smoke testing, pilot UAT,
backup/restore drills, or a deployment review.

## 3. Test Project Structure and Technology

The backend test files are located under:

``` text
ednerve-api/
└── tests/
    ├── TestCase.php
    ├── Unit/
    └── Feature/
        ├── AcademicCoreTest.php
        ├── AdmissionsTest.php
        ├── AttendanceTest.php
        ├── CourseStructureTest.php
        ├── CredentialsTest.php
        ├── ExamDutyTest.php
        ├── ExaminationTest.php
        ├── ExamSchedulingTest.php
        ├── PlatformAdministrationTest.php
        ├── QuestionBankTest.php
        ├── QuestionPaperApprovalTest.php
        ├── SecurityAndInstitutionIsolationTest.php
        ├── SecurityHeadersTest.php
        ├── SecurityIntegrityTest.php
        ├── SecurityMonitoringTest.php
        └── SyllabusTest.php
```

The saved project test inventory also included Laravel's example test.
The list above records the feature-test filenames found in the inspected
test inventory; it should be refreshed from the repository when new test
classes are added or renamed.

### Main testing components

  -----------------------------------------------------------------------
  Component                           Purpose
  ----------------------------------- -----------------------------------
  Laravel PHPUnit test runner         Discovers and executes automated
                                      tests

  `Tests\TestCase`                    Shared Laravel test base class

  `RefreshDatabase`                   Resets database state for isolated
                                      test execution

  Sanctum `actingAs()`                Authenticates the actor for API
                                      requests

  Eloquent models                     Creates realistic institution and
                                      domain records

  `postJson()`, `getJson()` and       Exercises API endpoints as HTTP
  related helpers                     requests

  `assertJsonPath()` /                Verifies API response contracts
  `assertJsonStructure()`             

  `assertDatabaseHas()` /             Verifies persisted state and
  `assertDatabaseMissing()`           prohibited side effects

  Direct DB assertions                Verifies relationships, audit
                                      records and compatibility tables
  -----------------------------------------------------------------------

The inspected `Tests\TestCase` is a thin extension of Laravel's base
test case. Feature-specific setup is implemented in each test class.

## 4. ExamDutyTest.php --- Test Design

### 4.1 File and test isolation

The test class is:

``` php
namespace Tests\Feature;

class ExamDutyTest extends TestCase
{
    use RefreshDatabase;
}
```

`RefreshDatabase` provides a clean database state for each test. This is
important because the tests create users, role assignments,
institutions, examinations, duty rosters and duty assignments.

The test class creates a dedicated institution named `Duty Test College`
and the roles needed by the workflows:

-   `institution_admin`
-   `faculty`
-   `examination_officer`

### 4.2 Test helper methods

The test class uses reusable helpers to avoid repeating setup logic.

  -----------------------------------------------------------------------
  Helper                              Responsibility
  ----------------------------------- -----------------------------------
  `setUp()`                           Creates the test institution and
                                      required role records

  `makeUser()`                        Creates an institution user and
                                      inserts an active primary role
                                      assignment

  `confirmMfa()`                      Creates a confirmed MFA record for
                                      test actors

  `createAcademicSession()`           Creates an institution-scoped
                                      academic session

  `createRelatedExam()`               Creates a related examination,
                                      roster and examination subject for
                                      workload scenarios

  `createDutyAssignment()`            Creates a duty assignment linked to
                                      the latest roster

  `seedExam()`                        Creates the programme, subject,
                                      examination and examination subject
                                      through the API

  `publishRoster()`                   Exercises submit, independent
                                      approval and publication

  `prepareSwapScenario()`             Prepares a published roster with
                                      two accepted assignments for
                                      swap-related scenarios
  -----------------------------------------------------------------------

The helpers keep test setup consistent while allowing each test to focus
on one business rule.

### 4.3 Authentication, roles and MFA

The test suite uses `Sanctum::actingAs($user)` to execute requests as
different actors.

The helper `confirmMfa()` inserts a confirmed MFA secret record for
actors who must pass privileged MFA checks. This allows tests to
exercise the application-level MFA gate without performing an
interactive authenticator flow.

The test actors represent the main duty workflow roles:

-   Institution administrator: creates and manages the draft roster.
-   Faculty/staff member: responds to their own published assignment.
-   Examination officer: independently approves, rejects or publishes
    the roster.

Tests should continue to distinguish authentication, authorization,
institution scope and MFA requirements. A request passing one of these
controls does not imply that it passes the others.

## 5. Exam Duty Functional Coverage

The following coverage areas are represented in the saved test source
and the development test history.

### 5.1 Duty roles and role seeding

Verify that:

-   Default examination duty roles are seeded.
-   An authorized user can retrieve the duty-role list.
-   Expected role codes such as `INVIGILATOR` are available.

This protects the configuration required by manual and automatic
assignment.

### 5.2 Manual assignment and legacy compatibility

Verify that:

-   An administrator can create a duty assignment.
-   The assignment is linked to the correct examination subject, staff
    member, duty role and roster.
-   The assignment is visible in the roster response.
-   The legacy `exam_invigilations` projection is synchronized at the
    correct lifecycle point.

A later legacy-sync regression test explicitly verifies that a draft
assignment does not appear in `exam_invigilations` and that publication
makes the assignment visible in that compatibility projection.

This is an important integration check because the new versioned
duty-assignment model and the legacy projection must not become
inconsistent.

### 5.3 Availability and automatic assignment

Verify that:

-   Staff availability is considered for the examination date.
-   An unavailable staff member is not selected.
-   An eligible staff member can be selected instead.
-   Automatic assignment creates the expected number of assignments.
-   Workload balancing is applied.

### 5.4 Assignment acceptance and decline

Verify that:

-   Staff can respond to their own assignment.
-   Acceptance and decline are recorded.
-   A staff member cannot respond to another staff member's assignment.
-   A response cannot be submitted twice.
-   A manager cannot bypass the staff-response workflow by directly
    setting the response status.

### 5.5 Cross-examination workload

The workload regression coverage includes the following business rules:

-   Eligible scheduled and published examinations in the same
    institution and academic session contribute to workload.
-   Completed examinations are excluded.
-   Examinations from a different academic session are excluded.
-   When the target examination has no academic session, workload is
    limited to that target examination.
-   Declined and replaced assignments do not increase active workload.
-   Roster workload and automatic assignment use consistent scope.

The development checklist records an earlier workload test checkpoint of
**93 tests passed and 498 assertions** for the backend baseline at that
point. This is a historical checkpoint, not the latest full-suite
result.

### 5.6 Versioned roster model and version history

The versioning tests protect the rule that each roster version has its
own assignment records and lifecycle.

Coverage includes:

-   Institution-scoped roster versions.
-   Version 1 and subsequent roster versions.
-   Assignment-to-roster linkage.
-   Creation of a new draft version.
-   History retrieval with lifecycle metadata.
-   Preservation of prior-version assignment records.
-   Copying eligible assignments into a new version as new records.
-   Resetting copied assignments to `assigned`, requiring a fresh staff
    response.
-   Keeping declined and replaced assignments in the prior version
    rather than treating them as active assignments in the new version.

### 5.7 Roster lifecycle and separation of duties

The implemented lifecycle is:

``` text
Draft
  |
  v
Submitted
  | \
  |  \--> Rejected
  v
Approved
  |
  v
Published
```

Automated checks cover:

-   An administrator can submit a draft roster.
-   A draft cannot be submitted twice.
-   A non-draft roster cannot be submitted.
-   Faculty cannot submit the roster.
-   An independent examination officer can approve a submitted roster.
-   The submitter cannot approve their own roster.
-   A draft roster cannot be approved.
-   Faculty cannot approve a roster.
-   An independent examination officer can reject a submitted roster.
-   The submitter cannot reject their own roster.
-   A draft roster cannot be rejected.
-   Faculty cannot reject a roster.
-   A rejection reason is mandatory.
-   Publication requires the correct preceding lifecycle state.
-   A draft, submitted or rejected roster cannot be published.
-   Faculty cannot publish an approved roster.
-   A published roster cannot be published again.
-   Structural assignment operations are rejected after publication.

The test helper `publishRoster()` models the intended separation: the
administrator submits, and a different MFA-confirmed examination officer
approves and publishes.

### 5.8 Institution isolation

The cross-institution test creates another institution and an
examination belonging to it. It then attempts to access that
examination's roster as users from the first institution.

The tested operations include:

-   Reading the other institution's roster.
-   Reading roster history.
-   Printing the roster.
-   Submitting the other institution's roster.
-   Approving, rejecting or publishing the other institution's roster.

The expected response is `404 Not Found` for cross-institution access
attempts. The test also checks that rejected attempts do not create
audit events and that the actor can still access their own institution's
examination.

### 5.9 Audit behaviour and integrity

The test history and project checklist record coverage for:

-   Lifecycle audit events.
-   Actor attribution.
-   Audit-chain verification.
-   No audit event for rejected cross-institution access attempts.
-   Preservation of audit history when roster versions change.

Audit integrity tests also exist in the separate `SecurityIntegrityTest`
suite.

### 5.10 Duty swap scenarios

The saved `ExamDutyTest` source includes a `prepareSwapScenario()`
helper that prepares:

-   An administrator.
-   Two faculty members.
-   A published roster.
-   Two assignments.
-   Acceptance of both assignments by their respective staff members.

The development history reports that duty-swap test scenarios were added
and exercised as part of the expanded Exam Duty work. However, a test
helper or test result does not, by itself, establish that every swap
workflow requirement is complete. Check the current implementation and
current `ExamDutyTest.php` before extending the report with detailed
swap-state guarantees.

## 6. Other Automated Feature-Test Suites

The inspected test inventory contains the following feature-test
classes. The descriptions below are limited to what the class names,
saved test output and inspected project notes support.

  -------------------------------------------------------------------------------
  Test class                                  Feature area / known purpose
  ------------------------------------------- -----------------------------------
  `AcademicCoreTest.php`                      Academic sessions,
                                              programme/subject setup, course
                                              mapping, session activation,
                                              institution isolation and faculty
                                              read/write permissions

  `AdmissionsTest.php`                        Public application submission and
                                              tracking, closed-intake rejection,
                                              DOB verification, admission
                                              review/enrolment, capacity and
                                              email-conflict checks, institution
                                              isolation

  `AttendanceTest.php`                        Timetable access, slot creation
                                              permissions, attendance
                                              open/save/summary, faculty
                                              ownership and deletion restrictions

  `CourseStructureTest.php`                   Subject component CRUD,
                                              prerequisite management,
                                              self/cyclic prerequisite rejection,
                                              institution boundaries and elective
                                              group lifecycle

  `CredentialsTest.php`                       Marksheet credential issue and
                                              verification, tampering,
                                              revocation, unknown credential and
                                              student authorization

  `ExaminationTest.php`                       Examination and result-related
                                              workflows; the saved test output
                                              includes revaluation and
                                              hall-ticket tests

  `ExamSchedulingTest.php`                    Exam scheduling, ordinance rules,
                                              room conflicts/capacity, attendance
                                              eligibility and hall-ticket
                                              override workflows

  `PlatformAdministrationTest.php`            Platform administration API
                                              coverage

  `QuestionBankTest.php`                      Question-bank API and data
                                              operations

  `QuestionPaperApprovalTest.php`             Question-paper approval and
                                              separation-of-duties workflows

  `SecurityAndInstitutionIsolationTest.php`   Security and institution-isolation
                                              checks

  `SecurityHeadersTest.php`                   HTTP security-header behaviour

  `SecurityIntegrityTest.php`                 Audit-chain integrity and
                                              result-history integrity checks

  `SecurityMonitoringTest.php`                Security-monitoring and
                                              audit-related checks

  `SyllabusTest.php`                          Syllabus feature API coverage
  -------------------------------------------------------------------------------

The class inventory is not a substitute for reading each class. For a
complete per-test catalogue, inspect the current source in
`tests/Feature/` and enumerate each `test_...` method and any data
providers.

### 6.1 Examples visible in saved full-suite output

The saved backend test output includes successful cases such as:

**Academic Core** - Institution administrator can create and read
academic sessions. - Session activation is exclusive. - Programme and
subject creation and course mapping. - Duplicate subject code
rejection. - Institution data isolation. - Faculty can read but cannot
write. - Programme course mapping can be removed and recreated.

**Admissions** - Public applicant can submit and track an application. -
Closed intake rejects submission. - Incorrect date of birth does not
reveal an application. - Administrator reviews and enrolls an admitted
applicant. - Faculty cannot review or enroll. - Enrollment is blocked by
foreign-account email conflict. - Enrollment is blocked when seat
capacity is full. - Applications are isolated by institution.

**Attendance** - Faculty can view a timetable while a student cannot. -
Administrator can create a slot while faculty cannot. - Attendance open,
save and summary flow. - Faculty cannot mark another faculty member's
slot. - A slot with attendance cannot be deleted.

**Course Structure** - Subject component upsert, index and delete. -
Faculty cannot mutate components. - Prerequisite CRUD and
self-prerequisite rejection. - Prerequisite cycle rejection. -
Cross-institution prerequisite rejection. - Elective-group lifecycle and
subject attachment. - Duplicate elective-group code rejection per
programme and semester.

**Credentials** - Administrator can issue and verify a marksheet
credential. - Tampered credential fails verification. - Revoked
credential shows revoked status. - Unknown credential returns not
found. - Student cannot issue credentials.

These examples demonstrate that the automation tests both positive
workflows and negative/security cases.

## 7. Test Data and Isolation Strategy

The inspected tests commonly use `RefreshDatabase` and create their own
records rather than relying on production data.

Typical test setup includes:

1.  Create an institution.
2.  Create the required platform/institution roles.
3.  Create users with an explicit `institution_id`.
4.  Create active role assignments in `user_role_assignments`.
5.  Confirm MFA records for privileged actors when required.
6.  Create academic and feature-specific records.
7.  Authenticate as the actor using Sanctum.
8.  Execute the API request.
9.  Assert response, database state and prohibited side effects.

### Important test-data practices

-   Use unique email addresses for each actor created within a test.
-   Keep test records institution-scoped.
-   Create the minimum required related data for each scenario.
-   Avoid depending on execution order between tests.
-   Avoid using real production accounts, credentials or database data.
-   Use factories where available and appropriate; use explicit model
    setup when a scenario requires precise relationships or states.

The test history contains an example of a
`UniqueConstraintViolationException` caused by reusing a user email
within a test. The correction principle is to give every distinct test
actor a unique email, or deliberately reuse the same actor instance when
that is the scenario being tested.

## 8. How to Run the Tests

Open PowerShell in the API project directory:

``` powershell
cd C:\xampp\htdocs\EdNerve\ednerve-api
```

### 8.1 Run the complete backend test suite

``` powershell
php artisan test
```

This is the main regression command. Run it after changes that may
affect multiple modules, shared authorization, middleware, migrations,
models, or services.

### 8.2 Run only ExamDutyTest

``` powershell
php artisan test --filter=ExamDutyTest
```

Use this for fast iteration while modifying duty roles, assignment
workflows, roster lifecycle, versioning, workload, swaps or related
services.

### 8.3 Run an individual test method

``` powershell
php artisan test --filter=test_duty_roles_seeded_and_listable
```

Replace the filter with the exact method name in the current test file.

### 8.4 Run a particular feature test file

``` powershell
php artisan test tests/Feature/ExamDutyTest.php
```

This is useful when the filter name is ambiguous or when a specific test
class must be executed directly.

### 8.5 Optional verbose output

``` powershell
php artisan test --testdox
```

Use this when reviewing test names and results in a readable format. The
exact output format depends on the installed Laravel/PHPUnit versions.

## 9. Recommended Test-Driven Workflow for Future Features

Use the following repeatable plan for Exam Duty and other OolaEdNerve
modules.

### Phase A --- Understand the requirement

1.  Identify the user roles involved.
2.  Identify the institution-owned records and required `institution_id`
    scope.
3.  Write down valid and invalid state transitions.
4.  Identify authorization and MFA gates.
5.  Identify database side effects and history requirements.
6.  Identify related legacy projections or integrations.

### Phase B --- Define the test scenarios

For each requirement, define at least:

-   A successful/allowed case.
-   An unauthorized actor case.
-   An invalid input or invalid state case.
-   A cross-institution access case where institution-owned data is
    involved.
-   A database assertion for the intended side effect.
-   A database assertion for side effects that must not occur.

For lifecycle features, test every transition and the prohibited
transitions. For versioned features, test both current-state behaviour
and historical preservation.

### Phase C --- Build isolated test data

1.  Use `RefreshDatabase`.
2.  Create a dedicated institution.
3.  Create roles and users required by the scenario.
4.  Assign roles through the same institution-scoped role-assignment
    structure used by the application.
5.  Confirm MFA for privileged actors when the endpoint requires it.
6.  Create only the required related records.
7.  Use unique test emails.

### Phase D --- Exercise the API

Use Laravel HTTP testing helpers such as:

``` php
$this->getJson('/api/example')->assertOk();

$this->postJson('/api/example', $payload)->assertCreated();
```

For authenticated endpoints:

``` php
Sanctum::actingAs($actor);
```

For JSON contracts:

``` php
$response
    ->assertOk()
    ->assertJsonPath('data.status', 'active')
    ->assertJsonStructure(['data' => ['id', 'status']]);
```

### Phase E --- Verify persistence and security

Use database assertions:

``` php
$this->assertDatabaseHas('example_records', [
    'institution_id' => $institution->id,
    'status' => 'active',
]);

$this->assertDatabaseMissing('example_records', [
    'institution_id' => $otherInstitution->id,
    'user_id' => $unauthorizedUser->id,
]);
```

For security-sensitive operations, also verify that rejected requests do
not create audit events or mutate related records.

### Phase F --- Run targeted and full regression tests

After implementing the feature:

1.  Run its focused test class.
2.  Fix failures and rerun the focused class.
3.  Run related feature suites.
4.  Run the complete `php artisan test` suite.
5.  Record test count, assertion count, failures and duration.
6.  Review the diff and update the canonical project checklist with the
    verified local evidence.

## 10. Handling Test Failures

When a test fails:

1.  Read the first failing test name and assertion.
2.  Identify whether the failure is in test setup, route registration,
    authorization, validation, business logic, database state or
    response serialization.
3.  Inspect the failing line and the corresponding
    controller/service/model/route.
4.  Check whether the expected behaviour is still the approved
    requirement.
5.  Correct the implementation or test fixture; do not weaken an
    assertion merely to get a green run.
6.  Rerun the failing test in isolation.
7.  Rerun the entire feature class.
8.  Run the full suite to detect regressions elsewhere.

### Common failure patterns seen in the development history

  --------------------------------------------------------------------------
  Failure                                Diagnostic direction
  -------------------------------------- -----------------------------------
  `UniqueConstraintViolationException`   Reused email in test data; use
  on `users.email`                       unique actor emails or reuse the
                                         same actor instance intentionally

  Expected `200`/`201`, received `404`   Check route registration, route
                                         prefix, HTTP method, endpoint path
                                         and route middleware

  Expected forbidden response, received  Check actor role, institution
  another status                         scope, MFA setup and
                                         policy/middleware order

  Database assertion fails               Check whether the mutation should
                                         occur at draft, approval or
                                         publication time; verify
                                         transaction and version linkage

  Test passes alone but fails in full    Check isolation, shared static
  suite                                  state, non-unique data, event
                                         listeners and external side effects

  Audit count unexpectedly changes       Inspect audit-event creation and
                                         ensure rejected requests do not
                                         produce prohibited events
  --------------------------------------------------------------------------

## 11. Test Evidence and Historical Checkpoints

The following results were recorded during the development work. They
represent different points in the evolution of the test suite and should
not be added together.

  -----------------------------------------------------------------------
  Checkpoint              Result                  Context
  ----------------------- ----------------------- -----------------------
  21 Nov 2026 baseline    88 tests, 470           Backend regression
                          assertions, 0 failures, baseline recorded
                          5.27 seconds            before the later
                                                  workload and
                                                  roster-version
                                                  expansions

  21 Nov 2026 workload    93 tests, 498           Workload and
  checkpoint              assertions, 0 failures, auto-assignment scope
                          5.77 seconds            checks added

  22 Nov 2026             42 tests, 419           Targeted `ExamDutyTest`
  roster-version          assertions, 3.91        result recorded in the
  checkpoint              seconds                 development checklist
                                                  at that checkpoint

  Latest result reported  51 tests, 615           Expanded `ExamDutyTest`
  in the development      assertions, 5.02        after roster lifecycle
  conversation            seconds                 and duty-swap test work

  Latest full-suite       132 tests, 1,021        Full backend regression
  result reported in the  assertions, 11.82       run after the
  development             seconds                 swap-related work
  conversation                                    
  -----------------------------------------------------------------------

**Evidence discipline:** The latest reported result is 51/615 for the
targeted Exam Duty suite and 132/1,021 for the full suite. Re-run both
commands from the current repository before treating these numbers as
the current baseline. Keep the test output or CI artifact with the
commit that it verifies.

## 12. What Passing Automated Tests Establish --- and What They Do Not

Passing automated tests provide evidence that the tested local code
paths behaved as expected against the test database and configured test
environment.

They do not, by themselves, establish:

-   That the latest code has been deployed to production.
-   That production migrations and seeders have been applied safely.
-   That production credentials and privileged MFA are configured
    correctly.
-   That production backups can be restored.
-   That email, notifications or other external integrations work in the
    target environment.
-   That pilot users have completed UAT.
-   That every possible business scenario is covered by the current test
    suite.

Continue to distinguish local verification `[L]` from production
verification `[P]` in the project checklist.

## 13. Maintenance Checklist

Use this checklist whenever adding or modifying backend functionality.

-   [ ] Requirement and valid/invalid state transitions documented.
-   [ ] Positive workflow tests added.
-   [ ] Negative validation and invalid-state tests added.
-   [ ] Role and permission tests added.
-   [ ] MFA tests added for privileged operations where required.
-   [ ] Institution-isolation tests added for institution-owned data.
-   [ ] Database persistence and prohibited side effects asserted.
-   [ ] Audit behaviour tested for sensitive operations.
-   [ ] Historical/version behaviour tested where applicable.
-   [ ] Targeted feature test passes.
-   [ ] Related feature tests pass.
-   [ ] Full `php artisan test` suite passes.
-   [ ] Test count, assertions, duration and commit recorded.
-   [ ] Canonical `checklist.md` updated with local evidence.
-   [ ] Production status left unclaimed until production verification.

## 14. Final Status

The backend test automation is structured around Laravel feature tests,
isolated database state, Sanctum-authenticated API requests, and
assertions over HTTP responses and database state.

`ExamDutyTest.php` is a substantial regression suite for duty roles,
assignment, availability, staff responses, workload, versioned rosters,
lifecycle controls, institution isolation and audit-related behaviour.
Other feature suites cover academic core, admissions, attendance, course
structure, credentials, examinations, scheduling, question banks,
question-paper approval, platform administration, security and syllabi.

The latest reported local results were:

-   **ExamDutyTest:** 51 passed, 615 assertions.
-   **Full backend suite:** 132 passed, 1,021 assertions.

For future work, preserve the same discipline: test the business rule,
the unauthorized path, the institution boundary, the database side
effect and the historical behaviour---not just the successful API
response.
