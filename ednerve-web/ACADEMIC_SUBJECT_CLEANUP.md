# Academic Subject Terminology Cleanup

This package is a complete web-project snapshot based on the supplied `src(20261128-084136).zip`, with the latest academic lint fixes applied.

## Important cleanup
The obsolete files below have been removed because the subject-based pages and service methods replace them:
- `src/modules/academic/pages/CourseRegistrationPage.tsx`
- `src/modules/academic/pages/ProgrammeCoursesPage.tsx`

If applying this as a patch to an existing project instead of replacing the project folder, delete those two files manually. TypeScript includes unreferenced `.tsx` files in the build, so leaving them behind causes the errors from the old Course-based implementation to persist.

## Verify
Run:
```bash
npm run lint
npm run build
```
