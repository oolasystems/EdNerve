# OolaEdNerve Frontend ESLint Cleanup Report

**Project:** OolaEdNerve Web Application\
**Application path:** `C:\xampp\htdocs\EdNerve\ednerve-web`\
**Stack:** React 19, TypeScript, Vite, ESLint, React Hooks ESLint
plugin\
**Outcome:** `npm run lint` and `npm run build` both passed after the
final fixes\
**Purpose:** Record the cleanup plan, the issues encountered, the
corrective patterns used, and the verification process for future
maintenance.

------------------------------------------------------------------------

## 1. Executive Summary

The frontend lint-cleanup exercise was carried out incrementally, module
by module. The objective was not merely to silence ESLint. The objective
was to remove unsafe TypeScript usage, correct React Hooks patterns,
stabilize memoization dependencies, and retain the existing application
behaviour.

The work was completed in batches so that each group of changes could be
applied and checked before moving to the next group.

The final verification reported by the developer was:

-   `npm run lint` --- passed.
-   `npm run build` --- passed.

The final Attendance warning was fixed separately, and a missing
`EMPTY_LIST` declaration introduced in the first Batch 10 package was
corrected before the final successful build.

## 2. Starting Point and Cleanup Strategy

The initial lint run reported **182 problems: 152 errors and 30
warnings**.

The cleanup strategy was:

1.  Fix all lint errors before spending time on warnings.
2.  Group files by application module or related functionality.
3.  Inspect the current source files supplied for each batch.
4.  Make narrowly scoped changes rather than broad refactoring.
5.  Avoid disabling ESLint rules or hiding warnings.
6.  Run lint after each applied batch and compare the problem count.
7.  Run the TypeScript/Vite production build to catch errors that lint
    alone may not detect.
8.  Address any regression before proceeding.
9.  Finish with a complete lint and build verification.

This approach kept changes reviewable and made it easier to identify
which batch introduced a problem.

## 3. Progress by Batch

The following table records the reported lint progression during the
cleanup.

  -----------------------------------------------------------------------------
  Stage         Area / batch      Lint problems          Errors        Warnings
                                after the batch                 
  ------------- --------------- --------------- --------------- ---------------
  Baseline      Initial                     182             152              30
                frontend lint                                   

  Batch 1       Application                 117              87              30
                foundation                                      

  Batch 2       Examinations                 65              41              24

  Batch 3       Academic                     45              21              24

  Batch 4       Attendance                   41              17              24

  Batch 5       Finance                      38              14              24

  Batch 6       Institution                  37              13              24
                Profile                                         

  Batch 7       Platform Access              35              11              24
                --- Roles                                       

  Batch 8       Platform                     31               9              22
                Configuration                                   

  Batch 9       Remaining lint               22               0              22
                errors                                          

  Batch 10 and  Warning                       0               0               0
  final         cleanup,                                        
  corrections   including                                       
                Attendance                                      
  -----------------------------------------------------------------------------

*The counts are the results reported during the work. The Batch 10
warning count reached zero after its final Attendance fix and the
ProgrammeCourses TypeScript correction.*

## 4. Step-by-Step Implementation Plan and Work Performed

### Step 1 --- Application foundation

**Files / areas:** Router, authentication context, theme provider,
institution layout, and platform-admin layout.

Work performed:

-   Added narrowly scoped React Refresh exceptions where route
    registries and hook exports required them.
-   Removed an effect-driven state update for drawer visibility.
-   Derived drawer visibility from the current pathname instead.

Verification reported after this batch:

-   87 errors and 30 warnings remained.
-   `npm run build` passed.

### Step 2 --- Examinations

**Area:** `src/modules/examinations`

Work performed:

-   Replaced explicit `any` usage with appropriate types, inferred
    types, or `unknown`.
-   Added types for duty-roster staff and status badges.
-   Corrected question difficulty, question type, and status typing.
-   Removed render-time mutation in `QuestionPaperPrintPage.tsx`;
    numbering was derived from section and item positions.
-   Stabilized fallback arrays used in memoized calculations.
-   Removed a synchronous state update from an effect in
    `ExamSetupPage`; reset behaviour was moved to the exam-selection
    handler.

A build issue was then identified: catch variables had been changed from
`any` to `unknown`, but the code still accessed nested Axios-style error
properties directly. The existing helper `getApiErrorMessage` was used
to handle unknown API errors safely. Imports and the affected file were
corrected, and the developer confirmed that the build passed.

Verification reported:

-   65 problems remained: 41 errors and 24 warnings.
-   No remaining lint errors in the Examinations module.
-   Build passed after the corrective changes.

### Step 3 --- Academic

**Area:** Academic pages, including calendar, course registration,
faculty workload, lesson plans, and syllabi.

Work performed:

-   Removed unsafe `any` usage.
-   Introduced structural types for students, courses, and elective
    groups.
-   Typed payloads and status tones.
-   Corrected staff filtering types.
-   Removed a synchronous state update from an effect in `SyllabiPage`;
    reset behaviour was moved to the modal-close interaction.

Verification reported:

-   45 problems remained: 21 errors and 24 warnings.
-   Build passed.

### Step 4 --- Attendance

**Initial files:** `AttendanceMarkPage.tsx` and `TimetablePage.tsx`.

Work performed:

-   Reused the existing `getApiErrorMessage` helper.
-   Replaced unsafe casts such as `(e as any)?.response?.data?.message`
    with typed handling of an `unknown` error.

Verification reported:

-   41 problems remained: 17 errors and 24 warnings.
-   Attendance had no remaining lint errors at that stage.
-   One warning in `AttendanceMarkPage.tsx` remained for the `slots`
    dependency used by `useMemo`.
-   Build passed.

That final warning was addressed later in Batch 10; see Section 5.

### Step 5 --- Finance

**Files:**

-   `FinanceStructuresPage.tsx`
-   `FinanceStudentLedgerPage.tsx`

Work performed:

-   Reused `getApiErrorMessage` for API errors.
-   Replaced an unsafe `any` cast for fee frequency with the existing
    form value's union type.
-   Preserved the frequency values: `annual`, `semester`, and
    `one_time`.

Verification reported:

-   38 problems remained: 14 errors and 24 warnings.
-   Build passed.

### Step 6 --- Institution Profile

**File:** `src/modules/institution/pages/InstitutionProfilePage.tsx`

Issue:

-   A form state update was being performed inside a `useEffect`.

Work performed:

-   Removed the effect and its import.
-   Added an explicit `handleEdit()` interaction to initialize the form
    from the current institution data.
-   Kept save, cancel, validation, and API behaviour intact.
-   Avoided resetting user edits merely because profile query data
    changed.

Verification reported:

-   37 problems remained: 13 errors and 24 warnings.
-   Build passed.

### Step 7 --- Platform Access: Roles

**File:** `src/modules/platform-access/pages/RolesPage.tsx`

Issues:

-   State updates were being performed inside effects to select a role
    and hydrate role-editing fields.

Work performed:

-   Removed the state-synchronizing effect.
-   Derived the active role from `selectedRoleId` or the first available
    role.
-   Stored local edits in a `roleDrafts` object keyed by role ID.
-   Derived displayed form values from the selected server role and any
    local draft.
-   Added focused draft-update and draft-clear handlers.
-   Cleared drafts after successful mutations and when a role was
    deleted.

Verification reported:

-   35 problems remained: 11 errors and 24 warnings.
-   Build passed.
-   A new fallback-array dependency warning was noted for the
    permissions list and handled during warning cleanup.

### Step 8 --- Platform Configuration

**File:**
`src/modules/platform-configuration/components/SettingsPage.tsx`

Issues:

-   An effect synchronized server settings into several local state
    variables.
-   A `groupsPayload` warning was also present.

Work performed:

-   Removed effect-driven hydration of `drafts`, `initials`, and
    `dirtyKeys`.
-   Used server settings as defaults and stored only local user edits.
-   Derived dirty keys by comparing edited values with the latest server
    values.
-   Made reset restore the server values by clearing local overrides.
-   Cleared drafts after successful save and refreshed the settings
    query.
-   Removed a redundant Boolean conversion in the Enabled/Disabled
    label.
-   Resolved the `groupsPayload` warning.

Verification reported:

-   31 problems remained: 9 errors and 22 warnings.
-   Build passed.

### Step 9 --- Remaining lint errors

**Files / areas:**

-   `VerifyCredentialPage.tsx`
-   `SecurityPage.tsx`
-   `StudentsPage.tsx`
-   `students/api.ts`

Work performed:

-   Added explicit response and subject-result types in
    `VerifyCredentialPage.tsx`.
-   Replaced unsafe `any` usage in `SecurityPage.tsx` with typed query
    props and `unknown` error handling through `getApiErrorMessage`.
-   Replaced an empty interface in `students/api.ts` with
    `Record<string, never>`.
-   Removed an effect-driven state synchronization in
    `StudentsPage.tsx`; student detail was derived from query data.

Verification reported:

-   **22 problems: 0 errors and 22 warnings.**

This was the milestone at which error cleanup was complete and the work
moved to warnings.

### Step 10 --- Warning cleanup

The remaining warnings were reviewed by category rather than suppressed.

#### 10.1 Unstable fallback arrays in memoized calculations

Many pages used expressions such as:

``` tsx
const rows = query.data?.rows ?? [];
```

A new empty array can be created whenever the query data is absent. If
that array is used as a `useMemo` dependency, its reference can change
between renders and cause unnecessary recalculation.

The cleanup used stable fallback references or moved the fallback into a
memoized calculation, depending on the source file and its types.

The affected areas included:

-   Academic Calendar
-   Academic Sections
-   Academic Sessions
-   Academic Subjects
-   Elective Groups
-   Faculty Workload
-   Lesson Plans
-   Programme Courses
-   Programmes
-   Syllabi
-   Admission Intakes
-   Institution Academic Units
-   Institution Assignments
-   Institution Users
-   Platform Access Roles
-   Platform Users --- Effective Access Panel

The exact implementation was chosen per file so that the array element
types and existing query response contracts were preserved.

#### 10.2 Missing hook dependencies in Academic Units

`AcademicUnitsPage.tsx` had memoized filtering logic that referenced
`filterList` without declaring it as a dependency.

The filtering helper was stabilized with `useCallback`, and the relevant
memoized calculations were given the appropriate dependencies. This
addressed the dependency warnings without disabling the hook rule.

#### 10.3 React Hook Form `watch` warning

`AddInstitutionPage.tsx` used React Hook Form's `watch("name")` in a way
flagged by the React Hooks compatibility rule.

The form subscription was changed to `useWatch` using the form control
and the `name` field. The automatic slug-generation behaviour was
retained, including the rule that a manually edited slug should not be
overwritten.

#### 10.4 Final Attendance warning

After the main warning-cleanup package, one warning remained in:

`src/modules/attendance/pages/AttendanceMarkPage.tsx`

The `slots` value was selected conditionally from the administrator or
faculty query data. The fallback arrays could produce a new reference
and make the `selectedEntry` memo dependency unstable.

The final correction separated the two query results and memoized the
selection:

``` tsx
const adminSlots = adminSlotsQuery.data?.timetable;
const facultySlots = facultySlotsQuery.data?.timetable;

const slots = useMemo(
  () => (canManage ? adminSlots ?? [] : facultySlots ?? []),
  [canManage, adminSlots, facultySlots]
);
```

The existing `selectedEntry` memo could then depend on the stable
`slots` reference and `entryId`.

#### 10.5 Programme Courses TypeScript correction

The first warning-cleanup package referenced `EMPTY_LIST` in
`ProgrammeCoursesPage.tsx` but omitted its declaration. This caused a
TypeScript build failure and consequent implicit-`any` errors in course
and subject callbacks.

The correction added a module-level stable fallback:

``` tsx
const EMPTY_LIST: never[] = [];
```

This allowed the query response types to provide the element types for
`mappedCourses` and `allSubjects`, while retaining a stable empty
fallback.

The developer then confirmed that both lint and build passed.

## 5. Important Technical Patterns to Reuse

### Pattern A --- Handle API errors as `unknown`

Prefer the shared helper:

``` tsx
import { getApiErrorMessage } from "../../../shared/lib/api-error";

try {
  // API operation
} catch (error) {
  setError(getApiErrorMessage(error, "Unable to complete the operation."));
}
```

Avoid:

``` tsx
catch (error) {
  setError((error as any)?.response?.data?.message);
}
```

This keeps error handling consistent and avoids unsafe property access.

### Pattern B --- Keep stable fallback arrays

For a typed module-level fallback, use a stable constant where
appropriate:

``` tsx
const EMPTY_LIST: never[] = [];
```

Then:

``` tsx
const rows = query.data?.rows ?? EMPTY_LIST;
```

Use this only when the query's declared response type supplies the
intended array element type. Do not use `never[]` as a substitute for a
missing or incorrect API response type.

An alternative is to place the fallback inside the memo callback when
the array is only needed for that calculation:

``` tsx
const filtered = useMemo(
  () => (query.data?.rows ?? []).filter(/* existing predicate */),
  [query.data?.rows, searchTerm]
);
```

Choose the pattern that gives the clearest and correctly typed
dependencies.

### Pattern C --- Do not synchronize derived state with effects

If a value can be derived from props, query data, or existing state,
derive it during render rather than copying it into state through an
effect.

For editable forms, initialize or reset the draft in an explicit user
interaction such as Edit, Create, or Reset. This avoids accidental
overwriting of in-progress user edits.

### Pattern D --- Stabilize helper functions used by memoized calculations

When a helper is referenced by `useMemo` or `useEffect`, either:

-   declare it with `useCallback` and include its real dependencies, or
-   restructure the calculation so that the helper is local to the memo
    callback.

Do not omit dependencies merely to satisfy lint.

### Pattern E --- Keep types explicit at API boundaries

Use the API response and payload interfaces already defined by the
application. Avoid `any`. Use `unknown` for values whose structure is
not known, and narrow them or pass them to a shared error-handling
function.

### Pattern F --- Use `useWatch` for targeted React Hook Form subscriptions

When a component needs to react to one form field, use `useWatch` with
the form's `control` and the field name. Preserve any existing user-edit
precedence rules, such as not overwriting a manually edited slug.

## 6. Verification Procedure for Future Changes

Use the following procedure whenever lint-related changes are made.

### Before editing

1.  Confirm the working branch and current project state.
2.  Run `npm run lint` and record the baseline.
3.  Run `npm run build` to establish whether the project already builds.
4.  Inspect the current source files; do not patch against an old copy.
5.  Group related files into a small reviewable batch.

### After each batch

1.  Apply only the files in that batch.
2.  Run `npm run lint`.
3.  Compare the error and warning counts with the previous result.
4.  Inspect any new warning or error rather than assuming it is
    unrelated.
5.  Run `npm run build`.
6.  Correct build regressions before moving to the next batch.
7.  Review the diff to ensure application behaviour has not been
    unintentionally changed.

### Final verification

Run both commands from the web application root:

``` powershell
npm run lint
npm run build
```

Record the date, branch/commit, and complete command output in the
project's development notes or release evidence.

Do not describe the application as production-ready solely because lint
and build pass. Those checks establish code-quality and compilation
results, not production configuration, security, integration, or
user-acceptance readiness.

## 7. Lessons Learned

1.  **Lint and build are complementary.** ESLint can pass while
    TypeScript compilation still fails, as demonstrated by the missing
    `EMPTY_LIST` declaration.
2.  **Fix errors before warnings.** This reduced the scope of the
    remaining work and made warning cleanup more focused.
3.  **Use current source files.** Each batch should be based on the
    files currently in the developer's working tree.
4.  **Avoid broad automated replacements.** TypeScript types and API
    response shapes differ between modules.
5.  **Do not suppress rules as a shortcut.** The cleanup focused on
    correcting the underlying hook, type, and dependency issues.
6.  **Explicit user actions are often better than synchronization
    effects.** Edit, reset, and selection handlers make state
    transitions easier to reason about.
7.  **Stable references matter.** Empty fallback arrays and helper
    functions can affect memoization and hook dependencies.
8.  **Verify each batch.** Small batches make regressions easier to
    isolate and correct.

## 8. Completion Checklist

-   [x] Initial lint baseline recorded.
-   [x] Lint errors reduced to zero.
-   [x] Remaining warnings addressed.
-   [x] Final Attendance warning addressed.
-   [x] Programme Courses TypeScript regression corrected.
-   [x] `npm run lint` passed.
-   [x] `npm run build` passed.
-   [ ] Record the final Git commit hash and verification date in the
    project repository.

## 9. Final Status

**Frontend lint cleanup: completed.**

The developer confirmed that both `npm run lint` and `npm run build`
passed after the final corrections.

This report is intended as a repeatable maintenance reference. For
future lint work, follow the same sequence: establish a baseline,
inspect current source, fix errors first, address warnings by root
cause, verify each batch, and finish with both lint and build.
