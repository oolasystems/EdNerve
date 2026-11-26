<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a published standard admission form for each existing admission intake.
 * Idempotent: safe to run repeatedly in local/demo environments.
 *
 * This is a baseline form definition. Institutions can customize later through
 * versioned form configuration; this seeder does not create applicant accounts.
 */
class AdmissionFormSeeder extends Seeder
{
    public function run(): void
    {
        $intakes = DB::table('admission_intakes')->get();

        $sections = [
            ['key' => 'programme', 'title' => 'Programme and Admission Details', 'order' => 1, 'required' => true],
            ['key' => 'personal', 'title' => 'Personal Details', 'order' => 2, 'required' => true],
            ['key' => 'contact', 'title' => 'Contact and Address', 'order' => 3, 'required' => true],
            ['key' => 'guardian', 'title' => 'Parent / Guardian Details', 'order' => 4, 'required' => false],
            ['key' => 'category', 'title' => 'Category and Special Provisions', 'order' => 5, 'required' => false],
            ['key' => 'academic', 'title' => 'Academic Qualifications', 'order' => 6, 'required' => true],
            ['key' => 'entrance', 'title' => 'Entrance Examination Details', 'order' => 7, 'required' => false],
            ['key' => 'experience', 'title' => 'Work Experience', 'order' => 8, 'required' => false],
            ['key' => 'research', 'title' => 'Research Details', 'order' => 9, 'required' => false],
            ['key' => 'documents', 'title' => 'Document Checklist', 'order' => 10, 'required' => true],
            ['key' => 'declarations', 'title' => 'Declarations and Consent', 'order' => 11, 'required' => true],
        ];

        $fields = [
            'programme' => [
                ['programme_code', 'Programme', 'text', true],
                ['admission_category', 'Admission Category / Quota', 'text', false],
            ],
            'personal' => [
                ['first_name', 'First Name', 'text', true],
                ['middle_name', 'Middle Name', 'text', false],
                ['last_name', 'Last Name', 'text', true],
                ['full_name_as_document', 'Name as per Official Document', 'text', false],
                ['date_of_birth', 'Date of Birth', 'date', true],
                ['gender', 'Gender', 'select', false],
                ['nationality', 'Nationality', 'text', true],
                ['category', 'Category', 'select', true],
            ],
            'contact' => [
                ['email', 'Email Address', 'email', true],
                ['mobile', 'Mobile Number', 'phone', true],
                ['alternate_mobile', 'Alternate Mobile Number', 'phone', false],
                ['correspondence_address', 'Correspondence Address', 'textarea', true],
                ['permanent_address', 'Permanent Address', 'textarea', false],
                ['city', 'City / Town', 'text', true],
                ['state', 'State / Province', 'text', true],
                ['postal_code', 'PIN / Postal Code', 'text', true],
                ['country', 'Country', 'text', true],
            ],
            'guardian' => [
                ['father_name', "Father's Name", 'text', false],
                ['mother_name', "Mother's Name", 'text', false],
                ['guardian_name', 'Guardian Name', 'text', false],
                ['guardian_relationship', 'Guardian Relationship', 'text', false],
                ['guardian_mobile', 'Parent / Guardian Mobile', 'phone', false],
                ['family_income', 'Annual Family Income', 'number', false],
            ],
            'category' => [
                ['reservation_category', 'Reservation Category', 'select', false],
                ['special_quota', 'Special Quota / Provision', 'text', false],
                ['disability_declaration', 'Disability / Assistance Declaration', 'boolean', false],
            ],
            'academic' => [
                ['qualification_level', 'Qualification Level', 'select', true],
                ['qualification_name', 'Qualification / Examination', 'text', true],
                ['board_university', 'Board / University', 'text', true],
                ['passing_year', 'Passing Year', 'number', true],
                ['result_status', 'Result Status', 'select', true],
                ['marks_obtained', 'Marks Obtained', 'number', false],
                ['maximum_marks', 'Maximum Marks', 'number', false],
                ['percentage_or_cgpa', 'Percentage / CGPA', 'number', false],
                ['subjects_stream', 'Main Subjects / Stream', 'text', false],
            ],
            'entrance' => [
                ['entrance_exam_name', 'Entrance Examination', 'text', false],
                ['entrance_exam_year', 'Examination Year', 'number', false],
                ['entrance_roll_number', 'Roll Number', 'text', false],
                ['entrance_score', 'Score', 'number', false],
                ['entrance_percentile', 'Percentile', 'number', false],
                ['entrance_rank', 'Rank', 'number', false],
            ],
            'experience' => [
                ['employer_name', 'Employer / Organization', 'text', false],
                ['designation', 'Designation', 'text', false],
                ['experience_start_date', 'Start Date', 'date', false],
                ['experience_end_date', 'End Date', 'date', false],
                ['experience_description', 'Experience Description', 'textarea', false],
            ],
            'research' => [
                ['research_area', 'Proposed Research Area', 'textarea', false],
                ['research_title', 'Proposed Research Title', 'text', false],
                ['research_synopsis', 'Research Synopsis', 'textarea', false],
                ['research_publications', 'Publications / Research Experience', 'textarea', false],
                ['research_exam_details', 'NET / JRF / GATE or Other Exam Details', 'textarea', false],
            ],
            'documents' => [
                ['photograph', 'Recent Photograph', 'file', true],
                ['signature', 'Applicant Signature', 'file', false],
                ['identity_proof', 'Identity Proof', 'file', false],
                ['date_of_birth_proof', 'Date of Birth Proof', 'file', false],
                ['academic_certificates', 'Academic Marksheets / Certificates', 'file', true],
                ['category_certificate', 'Category Certificate', 'file', false],
                ['entrance_scorecard', 'Entrance Scorecard', 'file', false],
                ['research_proposal', 'Research Proposal / Synopsis', 'file', false],
                ['experience_certificate', 'Experience Certificate', 'file', false],
                ['other_documents', 'Other Supporting Documents', 'file', false],
            ],
            'declarations' => [
                ['information_declaration', 'Declaration of Correct Information', 'boolean', true],
                ['privacy_notice_acknowledgement', 'Privacy Notice Acknowledgement', 'boolean', true],
                ['admission_rules_acknowledgement', 'Admission Rules Acknowledgement', 'boolean', true],
            ],
        ];

        foreach ($intakes as $intake) {
            $templateCode = 'INTAKE-' . $intake->id . '-STANDARD';
            DB::table('admission_form_templates')->updateOrInsert(
                ['institution_id' => $intake->institution_id, 'code' => $templateCode],
                [
                    'programme_id' => $intake->programme_id,
                    'intake_id' => $intake->id,
                    'name' => 'Standard Admission Form - ' . $intake->name,
                    'description' => 'Common admission form baseline. Configure requirements for the programme and intake.',
                    'status' => 'published',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $templateId = DB::table('admission_form_templates')
                ->where('institution_id', $intake->institution_id)
                ->where('code', $templateCode)
                ->value('id');

            DB::table('admission_form_versions')->updateOrInsert(
                ['template_id' => $templateId, 'version_number' => 1],
                [
                    'institution_id' => $intake->institution_id,
                    'status' => 'published',
                    'published_at' => now(),
                    'published_by' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $versionId = DB::table('admission_form_versions')
                ->where('template_id', $templateId)
                ->where('version_number', 1)
                ->value('id');

            foreach ($sections as $section) {
                DB::table('admission_form_sections')->updateOrInsert(
                    ['form_version_id' => $versionId, 'section_key' => $section['key']],
                    [
                        'institution_id' => $intake->institution_id,
                        'title' => $section['title'],
                        'description' => null,
                        'sort_order' => $section['order'],
                        'is_visible' => true,
                        'is_required' => $section['required'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $sectionId = DB::table('admission_form_sections')
                    ->where('form_version_id', $versionId)
                    ->where('section_key', $section['key'])
                    ->value('id');

                foreach (($fields[$section['key']] ?? []) as $position => $field) {
                    DB::table('admission_form_fields')->updateOrInsert(
                        ['section_id' => $sectionId, 'field_key' => $field[0]],
                        [
                            'institution_id' => $intake->institution_id,
                            'label' => $field[1],
                            'field_type' => $field[2],
                            'help_text' => null,
                            'validation_rules' => null,
                            'visibility_rule' => null,
                            'is_required' => $field[3],
                            'is_visible' => true,
                            'is_applicant_editable' => !in_array($field[0], ['programme_code', 'email', 'mobile'], true),
                            'is_sensitive' => in_array($field[0], ['category', 'reservation_category', 'disability_declaration', 'family_income'], true),
                            'sort_order' => $position + 1,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );

                    $fieldId = DB::table('admission_form_fields')
                        ->where('section_id', $sectionId)
                        ->where('field_key', $field[0])
                        ->value('id');

                    $optionSets = [
                        'gender' => [
                            ['male', 'Male'], ['female', 'Female'], ['other', 'Other'], ['prefer_not_to_say', 'Prefer not to say'],
                        ],
                        'category' => [
                            ['general', 'General'], ['obc', 'OBC'], ['sc', 'SC'], ['st', 'ST'], ['ews', 'EWS'], ['other', 'Other'],
                        ],
                        'reservation_category' => [
                            ['general', 'General'], ['obc', 'OBC'], ['sc', 'SC'], ['st', 'ST'], ['ews', 'EWS'], ['other', 'Other'],
                        ],
                        'result_status' => [
                            ['passed', 'Passed'], ['appearing', 'Appearing'], ['awaited', 'Result Awaited'],
                        ],
                    ];

                    foreach (($optionSets[$field[0]] ?? []) as $optionOrder => $option) {
                        DB::table('admission_form_field_options')->updateOrInsert(
                            ['field_id' => $fieldId, 'option_value' => $option[0]],
                            [
                                'institution_id' => $intake->institution_id,
                                'option_label' => $option[1],
                                'sort_order' => $optionOrder + 1,
                                'is_active' => true,
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                    }
                }
            }
        }
    }
}
