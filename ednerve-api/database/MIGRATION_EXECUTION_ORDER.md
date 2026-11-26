# Database Migration Execution Order

All migration filenames use the requested prefix `2026_11_24_` and a unique six-digit sequence.
Laravel executes migrations in filename order. The sequence preserves the supplied migration dependency order,
resolves the duplicate former `2026_11_19_000069` timestamp, and appends the Admission Module schema.

**Important:** All 76 existing migration filenames have been renamed. The `migrations` table in an already-migrated
database still contains the old filenames. Laravel will therefore treat these renamed migrations as pending.
Do not run `php artisan migrate` against an existing database with this package. For a disposable local database,
use `php artisan migrate:fresh --seed`. For a database containing data you need to preserve, retain the original
migration filenames and create a separate forward-only migration rollout instead.

| Sequence | Migration | Purpose |
| 000000 | `2026_11_24_000000_create_cache_table.php` | cache |
| 000001 | `2026_11_24_000001_create_cache_locks_table.php` | cache locks |
| 000002 | `2026_11_24_000002_create_jobs_table.php` | jobs |
| 000003 | `2026_11_24_000003_create_job_batches_table.php` | job batches |
| 000004 | `2026_11_24_000004_create_failed_jobs_table.php` | failed jobs |
| 000005 | `2026_11_24_000005_create_personal_access_tokens_table.php` | personal access tokens |
| 000006 | `2026_11_24_000006_create_platform_settings_table.php` | platform settings |
| 000007 | `2026_11_24_000007_create_permissions_table.php` | permissions |
| 000008 | `2026_11_24_000008_create_roles_table.php` | roles |
| 000009 | `2026_11_24_000009_create_role_permission_table.php` | role permission |
| 000010 | `2026_11_24_000010_create_institutions_table.php` | institutions |
| 000011 | `2026_11_24_000011_create_faculties_table.php` | faculties |
| 000012 | `2026_11_24_000012_create_schools_table.php` | schools |
| 000013 | `2026_11_24_000013_create_academic_units_table.php` | academic units |
| 000014 | `2026_11_24_000014_create_users_table.php` | users |
| 000015 | `2026_11_24_000015_create_user_role_assignments_table.php` | user role assignments |
| 000016 | `2026_11_24_000016_create_audit_logs_table.php` | audit logs |
| 000017 | `2026_11_24_000017_create_audit_log_checkpoints_table.php` | audit log checkpoints |
| 000018 | `2026_11_24_000018_create_academic_sessions_table.php` | academic sessions |
| 000019 | `2026_11_24_000019_create_subjects_table.php` | subjects |
| 000020 | `2026_11_24_000020_create_programmes_table.php` | programmes |
| 000021 | `2026_11_24_000021_create_programme_courses_table.php` | programme courses |
| 000022 | `2026_11_24_000022_create_admission_intakes_table.php` | admission intakes |
| 000023 | `2026_11_24_000023_create_applications_table.php` | applications |
| 000024 | `2026_11_24_000024_create_student_profiles_table.php` | student profiles |
| 000025 | `2026_11_24_000025_create_fee_heads_table.php` | fee heads |
| 000026 | `2026_11_24_000026_create_fee_structures_table.php` | fee structures |
| 000027 | `2026_11_24_000027_create_student_fee_dues_table.php` | student fee dues |
| 000028 | `2026_11_24_000028_create_fee_payments_table.php` | fee payments |
| 000029 | `2026_11_24_000029_create_subject_components_table.php` | subject components |
| 000030 | `2026_11_24_000030_create_subject_prerequisites_table.php` | subject prerequisites |
| 000031 | `2026_11_24_000031_create_elective_groups_table.php` | elective groups |
| 000032 | `2026_11_24_000032_create_elective_group_subjects_table.php` | elective group subjects |
| 000033 | `2026_11_24_000033_create_syllabi_table.php` | syllabi |
| 000034 | `2026_11_24_000034_create_syllabus_units_table.php` | syllabus units |
| 000035 | `2026_11_24_000035_create_timetable_entries_table.php` | timetable entries |
| 000036 | `2026_11_24_000036_create_attendance_sheets_table.php` | attendance sheets |
| 000037 | `2026_11_24_000037_create_attendance_records_table.php` | attendance records |
| 000038 | `2026_11_24_000038_create_exam_sessions_table.php` | exam sessions |
| 000039 | `2026_11_24_000039_create_exam_centres_table.php` | exam centres |
| 000040 | `2026_11_24_000040_create_exam_rooms_table.php` | exam rooms |
| 000041 | `2026_11_24_000041_create_exams_table.php` | exams |
| 000042 | `2026_11_24_000042_create_exam_subjects_table.php` | exam subjects |
| 000043 | `2026_11_24_000043_create_exam_components_table.php` | exam components |
| 000044 | `2026_11_24_000044_create_exam_invigilations_table.php` | exam invigilations |
| 000045 | `2026_11_24_000045_create_marks_table.php` | marks |
| 000046 | `2026_11_24_000046_create_examination_ordinances_table.php` | examination ordinances |
| 000047 | `2026_11_24_000047_create_results_table.php` | results |
| 000048 | `2026_11_24_000048_create_results_history_table.php` | results history |
| 000049 | `2026_11_24_000049_create_revaluation_requests_table.php` | revaluation requests |
| 000050 | `2026_11_24_000050_create_exam_eligibilities_table.php` | exam eligibilities |
| 000051 | `2026_11_24_000051_create_exam_seatings_table.php` | exam seatings |
| 000052 | `2026_11_24_000052_create_exam_kinds_table.php` | exam kinds |
| 000053 | `2026_11_24_000053_create_assessment_schemes_table.php` | assessment schemes |
| 000054 | `2026_11_24_000054_create_assessment_scheme_components_table.php` | assessment scheme components |
| 000055 | `2026_11_24_000055_create_question_bank_questions_table.php` | question bank questions |
| 000056 | `2026_11_24_000056_create_question_paper_templates_table.php` | question paper templates |
| 000057 | `2026_11_24_000057_create_question_papers_table.php` | question papers |
| 000058 | `2026_11_24_000058_create_question_paper_sections_table.php` | question paper sections |
| 000059 | `2026_11_24_000059_create_question_paper_items_table.php` | question paper items |
| 000060 | `2026_11_24_000060_create_question_paper_approvals_table.php` | question paper approvals |
| 000061 | `2026_11_24_000061_create_credentials_table.php` | credentials |
| 000062 | `2026_11_24_000062_create_credential_verifications_table.php` | credential verifications |
| 000063 | `2026_11_24_000063_create_user_mfa_secrets_table.php` | user mfa secrets |
| 000064 | `2026_11_24_000064_create_mfa_recovery_codes_table.php` | mfa recovery codes |
| 000065 | `2026_11_24_000065_create_security_alerts_table.php` | security alerts |
| 000066 | `2026_11_24_000066_create_exam_duty_roles_table.php` | exam duty roles |
| 000067 | `2026_11_24_000067_create_exam_duty_availability_table.php` | exam duty availability |
| 000068 | `2026_11_24_000068_create_exam_duty_assignments_table.php` | exam duty assignments |
| 000069 | `2026_11_24_000069_create_academic_calendar_events_table.php` | academic calendar events |
| 000070 | `2026_11_24_000070_create_exam_duty_rosters_and_link_assignments.php` | exam duty rosters + link assignments |
| 000071 | `2026_11_24_000071_create_faculty_workload_entries_table.php` | faculty workload entries |
| 000072 | `2026_11_24_000072_create_lesson_plan_entries_table.php` | lesson plan entries |
| 000073 | `2026_11_24_000073_create_academic_sections_table.php` | academic sections |
| 000074 | `2026_11_24_000074_create_course_registrations_table.php` | course registrations |
| 000075 | `2026_11_24_000075_create_exam_duty_swap_requests_table.php` | exam duty swap requests |
| 000076 | `2026_11_24_000076_create_admission_applicants_table.php` | admission applicants |
| 000077 | `2026_11_24_000077_create_admission_applicant_verification_tokens_table.php` | admission applicant verification tokens |
| 000078 | `2026_11_24_000078_create_admission_form_templates_table.php` | admission form templates |
| 000079 | `2026_11_24_000079_create_admission_form_versions_table.php` | admission form versions |
| 000080 | `2026_11_24_000080_create_admission_form_sections_table.php` | admission form sections |
| 000081 | `2026_11_24_000081_create_admission_form_fields_table.php` | admission form fields |
| 000082 | `2026_11_24_000082_create_admission_form_field_options_table.php` | admission form field options |
| 000083 | `2026_11_24_000083_add_applicant_and_form_links_to_applications.php` | add applicant + form links to applications |
| 000084 | `2026_11_24_000084_create_admission_application_qualifications_table.php` | admission application qualifications |
| 000085 | `2026_11_24_000085_create_admission_application_entrance_exams_table.php` | admission application entrance exams |
| 000086 | `2026_11_24_000086_create_admission_application_experiences_table.php` | admission application experiences |
| 000087 | `2026_11_24_000087_create_admission_application_documents_table.php` | admission application documents |
| 000088 | `2026_11_24_000088_create_admission_application_answers_table.php` | admission application answers |
| 000089 | `2026_11_24_000089_create_admission_application_events_table.php` | admission application events |
| 000090 | `2026_11_24_000090_create_admission_clarification_requests_table.php` | admission clarification requests |
| 000091 | `2026_11_24_000091_create_admission_payment_orders_table.php` | admission payment orders |
| 000092 | `2026_11_24_000092_create_admission_payment_transactions_table.php` | admission payment transactions |
| 000093 | `2026_11_24_000093_create_admission_merit_lists_table.php` | admission merit lists |
| 000094 | `2026_11_24_000094_create_admission_merit_list_entries_table.php` | admission merit list entries |
| 000095 | `2026_11_24_000095_create_admission_counselling_rounds_table.php` | admission counselling rounds |
| 000096 | `2026_11_24_000096_create_admission_seat_allocations_table.php` | admission seat allocations |
| 000097 | `2026_11_24_000097_create_admission_offers_table.php` | admission offers |
| 000098 | `2026_11_24_000098_create_admission_offer_responses_table.php` | admission offer responses |
| 000099 | `2026_11_24_000099_create_admission_interview_schedules_table.php` | admission interview schedules |
| 000100 | `2026_11_24_000100_create_admission_interview_assessments_table.php` | admission interview assessments |
| 000101 | `2026_11_24_000101_link_entrance_exam_scorecard_documents.php` | link entrance exam scorecard documents |