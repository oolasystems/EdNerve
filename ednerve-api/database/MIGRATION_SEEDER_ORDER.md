# Migration and seeder ordering (2026-11-28)

## Migration sequence

All 101 migration files use the `2026_11_28_000000` through
`2026_11_28_000100` sequence. The order was generated from the actual
foreign-key dependency graph, not simply grouped by module. Every referenced
table is created before its dependent table; the graph has no unresolved
references or cycles (self-references are within the table being created).

The sequence retains the framework/platform tables first, then identity and
institution structure, academic foundations and curriculum, admissions,
student/finance operations, timetable/attendance, examinations and question
papers, security/credentials, and the later admissions workflow tables.
Within each dependency level, the original relative order is retained where
possible.

## Academic schema terminology

- `subjects` is the single subject master.
- `programme_subjects` maps a subject into a programme and semester.
- `elective_groups` defines the choice basket and `required_choices`.
- `elective_group_subjects` links an elective basket to `programme_subjects`
  from the same programme and semester.
- `subject_registrations` records an individual student's selection/status.
- `academic_unit_id` on subjects and programmes identifies the owning
  department/unit. It is nullable for records not yet assigned.
- Group-level elective capacity was removed. Seat capacity belongs at the
  actual subject offering/delivery level, which is not modelled by this group.

## Seeder order

`DatabaseSeeder` runs seeders in dependency order:

1. Institutions, roles, permissions and admission workflow permissions.
2. Faculties, schools and academic units.
3. Institution users and role assignments.
4. Academic sessions, subjects, programmes and programme-subject mappings.
5. Elective groups and their approved programme-subject options.
6. Subject components, prerequisites, draft syllabi and syllabus units.
7. Admission configuration, admission forms and student profiles.
8. Finance, timetable/attendance, examination, question bank, platform and exam defaults.

The sample curriculum/syllabus rows are illustrative draft data, not
institution-approved academic content.

## Why not seed every table?

Configuration and reference tables receive deterministic, idempotent sample
data where useful. Transactional and workflow tables (for example payment
transactions, admission events, application answers, marks, result history,
exam seating, revaluation requests, MFA recovery codes and audit events) are
not filled with fabricated activity merely to make every table non-empty.
Those rows should be created by exercising their respective workflows or by
purpose-built test fixtures.

Run `php artisan migrate:refresh --seed` only against a disposable local
development database after taking any backup you need. It drops and recreates
the schema.
