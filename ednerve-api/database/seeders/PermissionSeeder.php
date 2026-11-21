<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Permission catalogue. `code` is the stable contract used by the UI
 * and (later) by the `permission:<code>` middleware.
 */
class PermissionSeeder extends Seeder
{
    /**
     * @return array<int, array{code:string, name:string, module:string, description:string}>
     */
    private function catalogue(): array
    {
        return [
            // Platform administration
            ['code' => 'platform.institutions.view',   'name' => 'View institutions',              'module' => 'platform', 'description' => 'List and inspect institutions.'],
            ['code' => 'platform.institutions.create', 'name' => 'Create institutions',            'module' => 'platform', 'description' => 'Provision new institutions.'],
            ['code' => 'platform.institutions.update', 'name' => 'Update institutions',            'module' => 'platform', 'description' => 'Edit institution details.'],
            ['code' => 'platform.institutions.status', 'name' => 'Activate/deactivate institutions', 'module' => 'platform', 'description' => 'Enable or disable institution access.'],
            ['code' => 'platform.users.view',     'name' => 'View users',                     'module' => 'platform', 'description' => 'View users across institutions and the platform.'],
            ['code' => 'platform.users.manage',   'name' => 'Manage users',                   'module' => 'platform', 'description' => 'Activate/deactivate users and create platform admins.'],
            ['code' => 'platform.roles.view',     'name' => 'View roles',                     'module' => 'platform', 'description' => 'View roles, permissions and assignments platform-wide.'],
            ['code' => 'platform.roles.manage',   'name' => 'Manage roles',                   'module' => 'platform', 'description' => 'Create, update and map permissions for roles.'],
            ['code' => 'platform.permissions.view', 'name' => 'View permissions',             'module' => 'platform', 'description' => 'View the permission catalogue.'],
            ['code' => 'platform.settings.view',  'name' => 'View platform settings',         'module' => 'platform', 'description' => 'View global configuration values.'],
            ['code' => 'platform.settings.manage', 'name' => 'Manage platform settings',      'module' => 'platform', 'description' => 'Change global configuration values.'],
            ['code' => 'platform.audit_logs.view', 'name' => 'View audit logs',               'module' => 'platform', 'description' => 'Inspect platform audit activity.'],
            ['code' => 'platform.system_health.view', 'name' => 'View system health',         'module' => 'platform', 'description' => 'Monitor platform service health.'],

            // Institution administration
            ['code' => 'institution.settings.view',   'name' => 'View institution settings', 'module' => 'institution', 'description' => 'View institution profile and settings.'],
            ['code' => 'institution.settings.manage', 'name' => 'Manage institution settings', 'module' => 'institution', 'description' => 'Edit institution profile and settings.'],
            ['code' => 'academic_units.view',     'name' => 'View academic hierarchy',    'module' => 'institution', 'description' => 'View faculties, schools and academic units.'],
            ['code' => 'academic_units.manage',   'name' => 'Manage academic hierarchy',  'module' => 'institution', 'description' => 'Create, update and deactivate faculties, schools and academic units.'],
            ['code' => 'institution_users.view',      'name' => 'View institution users',    'module' => 'institution', 'description' => 'View users of the institution.'],
            ['code' => 'institution_users.manage',    'name' => 'Manage institution users',  'module' => 'institution', 'description' => 'Create, update and deactivate institution users.'],
            ['code' => 'role_assignments.view',       'name' => 'View role assignments',     'module' => 'institution', 'description' => 'View assignments across the institution.'],
            ['code' => 'role_assignments.manage',     'name' => 'Manage role assignments',   'module' => 'institution', 'description' => 'Assign and revoke roles for institution users.'],
            ['code' => 'roles.view',                  'name' => 'View roles',                'module' => 'institution', 'description' => 'View the role catalogue.'],
            ['code' => 'academic.sessions.view',      'name' => 'View academic sessions',    'module' => 'institution', 'description' => 'View academic sessions and the active session.'],
            ['code' => 'academic.sessions.manage',    'name' => 'Manage academic sessions',  'module' => 'institution', 'description' => 'Create, update, deactivate and activate academic sessions.'],
            ['code' => 'academic.programmes.view',    'name' => 'View programmes',           'module' => 'institution', 'description' => 'View programmes and their course maps.'],
            ['code' => 'academic.programmes.manage',  'name' => 'Manage programmes',         'module' => 'institution', 'description' => 'Create, update and deactivate programmes.'],
            ['code' => 'academic.subjects.view',      'name' => 'View subjects',             'module' => 'institution', 'description' => 'View the subject master.'],
            ['code' => 'academic.subjects.manage',    'name' => 'Manage subjects',           'module' => 'institution', 'description' => 'Create, update and deactivate subjects.'],
            ['code' => 'academic.syllabi.view',       'name' => 'View syllabi',              'module' => 'institution', 'description' => 'View syllabus versions and their publish state.'],
            ['code' => 'academic.syllabi.manage',     'name' => 'Manage syllabi',            'module' => 'institution', 'description' => 'Create, review, approve, publish and clone syllabus versions.'],
            ['code' => 'academic.elective_groups.view',  'name' => 'View elective groups',    'module' => 'institution', 'description' => 'View programme elective groups and their offered subjects.'],
            ['code' => 'academic.elective_groups.manage','name' => 'Manage elective groups',  'module' => 'institution', 'description' => 'Create and update elective groups and attach or detach subjects.'],
            ['code' => 'admissions.intakes.view',     'name' => 'View admission intakes',    'module' => 'institution', 'description' => 'List admission intakes and their applicant counts.'],
            ['code' => 'admissions.intakes.manage',   'name' => 'Manage admission intakes',  'module' => 'institution', 'description' => 'Create, edit and close admission intakes.'],
            ['code' => 'admissions.applications.view', 'name' => 'View applications',         'module' => 'institution', 'description' => 'List and inspect applications.'],
            ['code' => 'admissions.applications.review','name' => 'Review applications',      'module' => 'institution', 'description' => 'Screen, shortlist, and record admission decisions.'],
            ['code' => 'admissions.applications.enroll','name' => 'Enroll applicants',        'module' => 'institution', 'description' => 'Enroll admitted applicants and create student accounts.'],
            ['code' => 'students.view',              'name' => 'View students',             'module' => 'institution', 'description' => 'List students enrolled at the institution.'],
            ['code' => 'students.manage',            'name' => 'Manage student profiles',   'module' => 'institution', 'description' => 'Update student profiles and enrollment state.'],
            ['code' => 'finance.view',               'name' => 'View finance',              'module' => 'institution', 'description' => 'View fee structures, ledgers and receipts.'],
            ['code' => 'finance.manage',             'name' => 'Manage finance configuration','module' => 'institution', 'description' => 'Create and edit fee heads and structures.'],
            ['code' => 'finance.record_payments',    'name' => 'Record fee payments',       'module' => 'institution', 'description' => 'Receive fee payments and record receipts.'],
            ['code' => 'timetable.view',             'name' => 'View timetable',            'module' => 'institution', 'description' => 'View the institution timetable grid.'],
            ['code' => 'timetable.manage',           'name' => 'Manage timetable',          'module' => 'institution', 'description' => 'Create and edit timetable slots.'],
            ['code' => 'attendance.view',            'name' => 'View attendance',           'module' => 'institution', 'description' => 'View attendance registers and summaries.'],
            ['code' => 'attendance.mark',            'name' => 'Mark attendance',           'module' => 'institution', 'description' => 'Record attendance for assigned class periods.'],
            ['code' => 'examinations.view',          'name' => 'View examinations',         'module' => 'institution', 'description' => 'View examination schedules and results.'],
            ['code' => 'examinations.manage',        'name' => 'Manage examinations',       'module' => 'institution', 'description' => 'Create and edit examination schedules, components and hall duties.'],
            ['code' => 'examinations.record_marks',  'name' => 'Record examination marks',  'module' => 'institution', 'description' => 'Enter marks into examination components.'],
            ['code' => 'examinations.publish',       'name' => 'Publish examination results','module' => 'institution', 'description' => 'Compute and publish examination results.'],
            ['code' => 'question_bank.view',         'name' => 'View question bank',         'module' => 'institution', 'description' => 'View question bank questions and papers.'],
            ['code' => 'question_bank.manage',       'name' => 'Manage question bank',       'module' => 'institution', 'description' => 'Create and edit question bank questions and papers.'],
        ];
    }

    /** Default grants for institutional administrators. */
    private function institutionAdminGrants(): array
    {
        return [
            'institution.settings.view',
            'institution.settings.manage',
            'academic_units.view',
            'academic_units.manage',
            'institution_users.view',
            'institution_users.manage',
            'role_assignments.view',
            'role_assignments.manage',
            'roles.view',
            'academic.sessions.view',
            'academic.sessions.manage',
            'academic.programmes.view',
            'academic.programmes.manage',
            'academic.subjects.view',
            'academic.subjects.manage',
            'academic.syllabi.view',
            'academic.syllabi.manage',
            'academic.elective_groups.view',
            'academic.elective_groups.manage',
            'admissions.intakes.view',
            'admissions.intakes.manage',
            'admissions.applications.view',
            'admissions.applications.review',
            'admissions.applications.enroll',
            'students.view',
            'students.manage',
            'finance.view',
            'finance.manage',
            'finance.record_payments',
            'timetable.view',
            'timetable.manage',
            'attendance.view',
            'attendance.mark',
            'examinations.view',
            'examinations.manage',
            'examinations.record_marks',
            'examinations.publish',
            'question_bank.view',
            'question_bank.manage',
        ];
    }

    /** Grants for faculty-capacity staff (faculty / HOD / dean): view + mark own classes. */
    private function facultyStaffGrants(): array
    {
        return [
            'timetable.view',
            'attendance.view',
            'attendance.mark',
            'examinations.view',
        ];
    }

    /** Grants for registrars: visibility without marking. */
    private function registrarGrants(): array
    {
        return [
            'timetable.view',
            'attendance.view',
            'examinations.view',
        ];
    }

    /** Grants for examination officers: full examination lifecycle. */
    private function examinationOfficerGrants(): array
    {
        return [
            'examinations.view',
            'examinations.manage',
            'examinations.record_marks',
            'examinations.publish',
            'question_bank.view',
            'question_bank.manage',
        ];
    }

    public function run(): void
    {
        foreach ($this->catalogue() as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [
                    'name' => $permission['name'],
                    'module' => $permission['module'],
                    'description' => $permission['description'],
                    'status' => 'active',
                    'is_system' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $grantMap = [
            'institution_admin' => $this->institutionAdminGrants(),
            'faculty' => $this->facultyStaffGrants(),
            'hod' => $this->facultyStaffGrants(),
            'dean' => $this->facultyStaffGrants(),
            'registrar' => $this->registrarGrants(),
            'examination_officer' => $this->examinationOfficerGrants(),
        ];

        foreach ($grantMap as $roleCode => $grants) {
            $role = DB::table('roles')->where('code', $roleCode)->first();

            if (!$role) {
                continue;
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('code', $grants)
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert(
                    ['role_id' => $role->id, 'permission_id' => $permissionId],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }
}
