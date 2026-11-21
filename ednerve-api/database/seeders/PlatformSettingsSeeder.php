<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Global platform configuration defaults. Only applied for keys that
 * do not already exist — local/production overrides are never clobbered.
 */
class PlatformSettingsSeeder extends Seeder
{
    /**
     * @return array<int, array{key:string, group:string, type:string, label:string, description:string, value:mixed}>
     */
    private function defaults(): array
    {
        return [
            ['key' => 'system.app_name', 'group' => 'system', 'type' => 'string', 'label' => 'Application name', 'description' => 'Shown in the product header and notifications.', 'value' => 'OolaEdNerve'],
            ['key' => 'system.support_email', 'group' => 'system', 'type' => 'string', 'label' => 'Support email', 'description' => 'Where institutions reach Oola Systems support.', 'value' => 'support@oolasystems.com'],
            ['key' => 'system.maintenance_mode', 'group' => 'system', 'type' => 'boolean', 'label' => 'Maintenance mode', 'description' => 'Temporarily block logins for maintenance windows.', 'value' => false],
            ['key' => 'system.maintenance_message', 'group' => 'system', 'type' => 'string', 'label' => 'Maintenance message', 'description' => 'Shown to users while maintenance mode is on.', 'value' => ''],
            ['key' => 'system.default_timezone', 'group' => 'system', 'type' => 'string', 'label' => 'Default timezone', 'description' => 'Used for reporting and audit timestamps.', 'value' => 'Asia/Kolkata'],

            ['key' => 'academic.default_academic_year', 'group' => 'academic', 'type' => 'string', 'label' => 'Default academic year', 'description' => 'Pre-filled when creating new term/period records.', 'value' => '2026-27'],
            ['key' => 'academic.currency', 'group' => 'academic', 'type' => 'string', 'label' => 'Default currency', 'description' => 'ISO code used across fee and finance modules.', 'value' => 'INR'],
            ['key' => 'academic.date_format', 'group' => 'academic', 'type' => 'string', 'label' => 'Date format', 'description' => 'Display format for Date fields.', 'value' => 'DD MMM YYYY'],

            ['key' => 'features.applicant_portal_enabled', 'group' => 'features', 'type' => 'boolean', 'label' => 'Applicant portal', 'description' => 'Allow applicants to track application status.', 'value' => true],
            ['key' => 'features.parent_portal_enabled', 'group' => 'features', 'type' => 'boolean', 'label' => 'Parent portal', 'description' => 'Allow parents/guardians limited read access.', 'value' => true],
            ['key' => 'features.digital_transcripts_enabled', 'group' => 'features', 'type' => 'boolean', 'label' => 'Digital transcripts', 'description' => 'Enable digital transcript issuance.', 'value' => false],
        ];
    }

    public function run(): void
    {
        foreach ($this->defaults() as $setting) {
            DB::table('platform_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'group' => $setting['group'],
                    'type' => $setting['type'],
                    'label' => $setting['label'],
                    'description' => $setting['description'],
                    'value' => json_encode($setting['value']),
                    'is_secret' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
