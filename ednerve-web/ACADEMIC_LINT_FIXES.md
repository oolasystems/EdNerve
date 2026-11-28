# EdNerve Web — academic lint/build fixes

Apply this patch over the updated `ednerve-web` project.

## Required cleanup after extracting
The previous web folder may contain two obsolete pages from the pre-Subject terminology implementation.
They are intentionally absent from the updated source archive. Delete these stale files if they still exist:

- `src/modules/academic/pages/CourseRegistrationPage.tsx`
- `src/modules/academic/pages/ProgrammeCoursesPage.tsx`

The current routes and navigation use `SubjectRegistrationPage.tsx` and `ProgrammeSubjectsPage.tsx`.
A simple ZIP extraction does not delete files that are no longer present in an archive, which is why these old files can continue to produce TypeScript errors after an overlay update.

## Fixes included
- `attachElectiveGroupSubject` now submits `programme_subject_id` using its declared argument, matching the API validation contract.
- `AcademicSection` includes nullable `capacity`, which is present in the migration and used by the section editor.
- Fixed the `course` loop variable references in `ProgrammeSubjectsPage`.
- `SubjectRegistrationPage` reads the `subjects` property returned by `ProgrammeSubjectsResponse`.

## Validation
Run:

```bash
npm run lint
npm run build
```

If errors remain, share the complete output. Do not remove or rename other files based only on their filenames.
