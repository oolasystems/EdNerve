<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Adds granular admission workflow permissions without changing the existing
 * core permission catalogue. Safe to re-run.
 */
class AdmissionWorkflowPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['code' => 'admissions.forms.view', 'name' => 'View admission forms', 'description' => 'View admission form templates and published versions.'],
            ['code' => 'admissions.forms.manage', 'name' => 'Manage admission forms', 'description' => 'Create, configure and publish admission form versions.'],
            ['code' => 'admissions.applications.scrutinize', 'name' => 'Scrutinize applications', 'description' => 'Verify application details and request corrections.'],
            ['code' => 'admissions.documents.view', 'name' => 'View application documents', 'description' => 'View applicant-submitted documents.'],
            ['code' => 'admissions.documents.verify', 'name' => 'Verify application documents', 'description' => 'Verify or return applicant documents.'],
            ['code' => 'admissions.selection.manage', 'name' => 'Manage admission selection', 'description' => 'Manage entrance assessments, interviews and selection outcomes.'],
            ['code' => 'admissions.merit.publish', 'name' => 'Publish admission merit lists', 'description' => 'Approve and publish merit lists and selection results.'],
            ['code' => 'admissions.offers.manage', 'name' => 'Manage admission offers', 'description' => 'Issue, revise, withdraw and expire admission offers.'],
            ['code' => 'admissions.enrollment.approve', 'name' => 'Approve admission enrollment', 'description' => 'Approve final admission and trigger controlled student enrollment.'],
            ['code' => 'admissions.payments.view', 'name' => 'View admission payments', 'description' => 'View admission application and seat acceptance payment status.'],
            ['code' => 'admissions.payments.reconcile', 'name' => 'Reconcile admission payments', 'description' => 'Perform authorized payment reconciliation.'],
            ['code' => 'admissions.reports.view', 'name' => 'View admission reports', 'description' => 'View admission dashboards and reports.'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [
                    'name' => $permission['name'],
                    'module' => 'admissions',
                    'description' => $permission['description'],
                    'status' => 'active',
                    'is_system' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $grants = [
            'institution_admin' => array_column($permissions, 'code'),
            'admissions_officer' => [
                'admissions.intakes.view',
                'admissions.intakes.manage',
                'admissions.applications.view',
                'admissions.applications.review',
                'admissions.forms.view',
                'admissions.forms.manage',
                'admissions.applications.scrutinize',
                'admissions.documents.view',
                'admissions.documents.verify',
                'admissions.selection.manage',
                'admissions.merit.publish',
                'admissions.offers.manage',
                'admissions.payments.view',
                'admissions.reports.view',
            ],
            'registrar' => [
                'admissions.intakes.view',
                'admissions.applications.view',
                'admissions.forms.view',
                'admissions.documents.view',
                'admissions.merit.publish',
                'admissions.offers.manage',
                'admissions.enrollment.approve',
                'admissions.payments.view',
                'admissions.reports.view',
            ],
        ];

        foreach ($grants as $roleCode => $permissionCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if (!$roleId) {
                continue;
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('code', $permissionCodes)
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
}
