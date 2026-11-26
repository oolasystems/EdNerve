# OolaEdNerve Database Package

## Contents

- `migrations/` — all existing migrations renumbered into a unique execution sequence, followed by new Admission Module migrations.
- `seeders/` — existing seeders, plus `AdmissionWorkflowPermissionSeeder` and `AdmissionFormSeeder`.
- `MIGRATION_EXECUTION_ORDER.md` — complete ordered migration inventory.
- Existing factories and verification SQL are retained from the uploaded archive. The bundled SQLite database file was intentionally omitted because it contains a separate, older migration state and could be mistaken for the updated schema.

## Migration sequence

- Existing schema: sequences `000000` through `000075` (76 migrations).
- Admission Module additions: sequences `000076` through `000101` (26 migrations).
- Total: 102 uniquely numbered migrations.

The existing migration order is preserved as supplied, except that all filenames have the requested prefix and the duplicate former timestamp is now unique. New Admission Module tables are appended after all existing academic, student, finance, examination and security dependencies.

## New Admission Module tables

1. `admission_applicants` — institution-scoped applicant identity, separate from institutional `users`.
2. `admission_applicant_verification_tokens` — hashed, expiring, single-use verification/setup/reset tokens.
3. `admission_form_templates` — institution/programme/intake form template.
4. `admission_form_versions` — versioned form definitions.
5. `admission_form_sections` — ordered form sections.
6. `admission_form_fields` — configurable field definitions and validation metadata.
7. `admission_form_field_options` — select/radio option values.
8. Existing `applications` extended with nullable `applicant_id` and `form_version_id`, plus a tenant-scoped composite unique key and expanded status length.
9. `admission_application_qualifications` — repeatable academic qualification history.
10. `admission_application_entrance_exams` — external/university entrance examination details.
11. `admission_application_experiences` — work experience history.
12. `admission_application_documents` — private document metadata and review status.
13. `admission_application_answers` — versioned configurable-field answers.
14. `admission_application_events` — application lifecycle timeline.
15. `admission_clarification_requests` — requests for correction/additional information.
16. `admission_payment_orders` — fee/payment order records.
17. `admission_payment_transactions` — provider transaction/callback records.
18. `admission_merit_lists` — merit list headers and publication state.
19. `admission_merit_list_entries` — ranked application entries.
20. `admission_counselling_rounds` — intake counselling rounds.
21. `admission_seat_allocations` — counselling seat allocation records.
22. `admission_offers` — provisional/final admission offers.
23. `admission_offer_responses` — applicant accept/decline history.
24. `admission_interview_schedules` — interview/assessment schedules.
25. `admission_interview_assessments` — assessor scores and recommendations.
26. A deferred foreign key links entrance examination scorecards to uploaded application documents.

The applicant account is not an institutional ERP user. No applicant demo accounts are seeded.

## Seeder execution order

`DatabaseSeeder.php` now calls seeders in this order:

1. `InstitutionSeeder` — institutions required by tenant-scoped seed data.
2. `RoleSeeder` — system roles.
3. `PermissionSeeder` — existing permission catalogue and grants.
4. `AdmissionWorkflowPermissionSeeder` — additional granular admission permissions and role grants.
5. `AcademicUnitSeeder` — academic hierarchy.
6. `UserSeeder` — platform and demo institution users/role assignments.
7. `AcademicSeeder` — sessions, subjects and programmes.
8. `AdmissionSeeder` — sample admission intakes.
9. `AdmissionFormSeeder` — standard version-1 form template for each existing intake.
10. `StudentSeeder`
11. `FinanceSeeder`
12. `TimetableAttendanceSeeder`
13. `ExaminationSeeder`
14. `QuestionBankSeeder`
15. `PlatformSettingsSeeder`
16. `ExamDefaultsSeeder`

All new seeders use `updateOrInsert` patterns and are intended to be repeatable. `AdmissionFormSeeder` creates form configuration only; it does not create applicant accounts or sample applications.

## Safe local setup

**Only for a disposable local database where all data can be deleted:**

```bash
php artisan migrate:fresh --seed
```

For a new empty database:

```bash
php artisan migrate
php artisan db:seed
```

Do not run `migrate:fresh` on a database containing data that must be retained.

## Existing database warning — read before running

The 76 existing migration files have been renamed to the new timestamp prefix as requested. Laravel records migration filenames in the `migrations` table. Renaming an already-executed migration does not update those records. Consequently, an existing database will consider the renamed files to be pending and may attempt to recreate tables.

Therefore:

- For a clean, disposable development database, use `migrate:fresh --seed`.
- For any database whose data must be preserved, do not use this renamed migration set directly. Keep the original migration filenames in that environment and prepare a forward-only deployment set containing only new migrations.
- Do not use `migrate:refresh` or `migrate:fresh` on a valuable database.

## Verification performed on this package

- PHP syntax lint was run against all PHP files in this package.
- Result: all PHP files passed `php -l`.
- Migration filenames were checked for a contiguous sequence from `000000` to `000100`.
- No Laravel migration execution, database engine compatibility test, API test or frontend test is claimed by this package preparation.

## Important implementation follow-ups

- Configure the applicant authentication guard/provider and applicant-specific middleware before enabling applicant login.
- Configure private file storage and authorization before accepting document uploads.
- Implement server-side validation for configured form fields; JSON validation metadata must not be interpreted as executable code.
- Verify payment provider callbacks server-to-server before marking an order paid.
- The payment tables are provider-neutral storage foundations; no gateway is configured.
- Review and test the existing enrollment workflow for idempotency before enabling final enrollment.
