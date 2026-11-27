<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Resolved once per seeder run. Never hardcode demo passwords in source.
     * Set DEMO_SEED_PASSWORD in .env for repeatable local dev, or leave unset to
     * generate a strong random password that is printed once (new users only).
     */
    private ?string $resolvedSeedPassword = null;

    public function run(): void
    {
        $seedDemoUsers = filter_var(
            config('seeding.seed_users', false),
            FILTER_VALIDATE_BOOLEAN
        );

        DB::transaction(function () use ($seedDemoUsers) {

            /*
            |--------------------------------------------------------------------------
            | 1. Create Platform Admin (first user)
            |--------------------------------------------------------------------------
            */

            $this->createUser([
                'account_type' => 'platform',
                'institution_id' => null,
                'name' => 'Platform Administrator',
                'email' => 'platform.admin@ednerve.test',
                'phone' => null,
            ]);

            if (!$seedDemoUsers) {
                $this->command?->warn('SEED_DEMO_USERS is disabled; skipped demo institution users. Set SEED_DEMO_USERS=true only for local/demo environments.');
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Institution-wise sample users
            |--------------------------------------------------------------------------
            */

            $institutions = [
                'AMU' => [
                    'admin' => [
                        'name' => 'AMU Institution Admin',
                        'email' => 'admin.amu@ednerve.test',
                        'phone' => '9000000001',
                    ],
                    'registrar' => [
                        'name' => 'AMU Registrar',
                        'email' => 'registrar.amu@ednerve.test',
                        'phone' => '9000000002',
                    ],
                    'faculty' => [
                        'name' => 'AMU Faculty',
                        'email' => 'faculty.amu@ednerve.test',
                        'phone' => '9000000003',
                    ],
                    'student' => [
                        'name' => 'AMU Student',
                        'email' => 'student.amu@ednerve.test',
                        'phone' => '9000000004',
                    ],
                    'examofficer' => [
                        'name' => 'AMU Examination Officer',
                        'email' => 'examofficer.amu@ednerve.test',
                        'phone' => '9000000006',
                    ],
                    'admissions' => [
                        'name' => 'AMU Admissions Officer',
                        'email' => 'admissions.amu@ednerve.test',
                        'phone' => '9000000005',
                    ],
                ],

                'JNU' => [
                    'admin' => [
                        'name' => 'JNU Institution Admin',
                        'email' => 'admin.jnu@ednerve.test',
                        'phone' => '9000000011',
                    ],
                    'registrar' => [
                        'name' => 'JNU Registrar',
                        'email' => 'registrar.jnu@ednerve.test',
                        'phone' => '9000000012',
                    ],
                    'faculty' => [
                        'name' => 'JNU Faculty',
                        'email' => 'faculty.jnu@ednerve.test',
                        'phone' => '9000000013',
                    ],
                    'student' => [
                        'name' => 'JNU Student',
                        'email' => 'student.jnu@ednerve.test',
                        'phone' => '9000000014',
                    ],
                    'examofficer' => [
                        'name' => 'JNU Examination Officer',
                        'email' => 'examofficer.jnu@ednerve.test',
                        'phone' => '9000000016',
                    ],
                    'admissions' => [
                        'name' => 'JNU Admissions Officer',
                        'email' => 'admissions.jnu@ednerve.test',
                        'phone' => '9000000015',
                    ],
                ],

                'JMI' => [
                    'admin' => [
                        'name' => 'JMI Institution Admin',
                        'email' => 'admin.jmi@ednerve.test',
                        'phone' => '9000000021',
                    ],
                    'registrar' => [
                        'name' => 'JMI Registrar',
                        'email' => 'registrar.jmi@ednerve.test',
                        'phone' => '9000000022',
                    ],
                    'faculty' => [
                        'name' => 'JMI Faculty',
                        'email' => 'faculty.jmi@ednerve.test',
                        'phone' => '9000000023',
                    ],
                    'student' => [
                        'name' => 'JMI Student',
                        'email' => 'student.jmi@ednerve.test',
                        'phone' => '9000000024',
                    ],
                    'examofficer' => [
                        'name' => 'JMI Examination Officer',
                        'email' => 'examofficer.jmi@ednerve.test',
                        'phone' => '9000000026',
                    ],
                    'admissions' => [
                        'name' => 'JMI Admissions Officer',
                        'email' => 'admissions.jmi@ednerve.test',
                        'phone' => '9000000025',
                    ],
                ],

                'JSPM' => [
                    'admin' => [
                        'name' => 'JSPM Institution Admin',
                        'email' => 'admin.jspm@ednerve.test',
                        'phone' => '9000000031',
                    ],
                    'registrar' => [
                        'name' => 'JSPM Registrar',
                        'email' => 'registrar.jspm@ednerve.test',
                        'phone' => '9000000032',
                    ],
                    'faculty' => [
                        'name' => 'JSPM Faculty',
                        'email' => 'faculty.jspm@ednerve.test',
                        'phone' => '9000000033',
                    ],
                    'student' => [
                        'name' => 'JSPM Student',
                        'email' => 'student.jspm@ednerve.test',
                        'phone' => '9000000034',
                    ],
                    'examofficer' => [
                        'name' => 'JSPM Examination Officer',
                        'email' => 'examofficer.jspm@ednerve.test',
                        'phone' => '9000000036',
                    ],
                    'admissions' => [
                        'name' => 'JSPM Admissions Officer',
                        'email' => 'admissions.jspm@ednerve.test',
                        'phone' => '9000000035',
                    ],
                ],

                'SPPU' => [
                    'admin' => [
                        'name' => 'SPPU Institution Admin',
                        'email' => 'admin.sppu@ednerve.test',
                        'phone' => '9000000041',
                    ],
                    'registrar' => [
                        'name' => 'SPPU Registrar',
                        'email' => 'registrar.sppu@ednerve.test',
                        'phone' => '9000000042',
                    ],
                    'faculty' => [
                        'name' => 'SPPU Faculty',
                        'email' => 'faculty.sppu@ednerve.test',
                        'phone' => '9000000043',
                    ],
                    'student' => [
                        'name' => 'SPPU Student',
                        'email' => 'student.sppu@ednerve.test',
                        'phone' => '9000000044',
                    ],
                    'examofficer' => [
                        'name' => 'SPPU Examination Officer',
                        'email' => 'examofficer.sppu@ednerve.test',
                        'phone' => '9000000046',
                    ],
                    'admissions' => [
                        'name' => 'SPPU Admissions Officer',
                        'email' => 'admissions.sppu@ednerve.test',
                        'phone' => '9000000045',
                    ],
                ],
            ];

            /*
            |--------------------------------------------------------------------------
            | 3. Role mapping
            |--------------------------------------------------------------------------
            */

            $roleCodes = [
                'admin' => 'institution_admin',
                'registrar' => 'registrar',
                'faculty' => 'faculty',
                'student' => 'student',
                'admissions' => 'admissions_officer',
                'examofficer' => 'examination_officer',
            ];

            /*
            |--------------------------------------------------------------------------
            | 4. Create organization units and institution users
            |--------------------------------------------------------------------------
            */

            foreach ($institutions as $institutionCode => $users) {

                $institution = DB::table('institutions')
                    ->where('code', $institutionCode)
                    ->first();

                if (!$institution) {
                    throw new RuntimeException(
                        "Institution not found: {$institutionCode}. Run InstitutionSeeder first."
                    );
                }

                // Academic hierarchy is upserted by AcademicUnitSeeder;
                // CSE always exists for demo institutions after it runs.
                $department = DB::table('academic_units')
                    ->where('institution_id', $institution->id)
                    ->where('code', 'CSE')
                    ->first();

                foreach ($users as $userType => $userData) {

                    // Create institution user.
                    $user = $this->createUser([
                        'account_type' => 'institution',
                        'institution_id' => $institution->id,
                        'name' => $userData['name'],
                        'email' => $userData['email'],
                        'phone' => $userData['phone'],
                    ]);

                    // Retrieve the corresponding universal role.
                    $roleCode = $roleCodes[$userType];

                    $roleId = DB::table('roles')
                        ->where('code', $roleCode)
                        ->where('status', 'active')
                        ->value('id');

                    if (!$roleId) {
                        throw new RuntimeException(
                            "Role not found or inactive: {$roleCode}. Run RoleSeeder first."
                        );
                    }

                    // Faculty and Student are scoped to the CSE department.
                    // Other roles are institution-wide.
                    $organizationUnitId = in_array(
                        $userType,
                        ['faculty', 'student']
                    ) ? $department->id : null;

                    // Create or update role assignment.
                    DB::table('user_role_assignments')->updateOrInsert(
                        [
                            'institution_id' => $institution->id,
                            'user_id' => $user->id,
                            'role_id' => $roleId,
                            'academic_unit_id' => $organizationUnitId,
                        ],
                        [
                            'is_primary' => true,
                            'status' => 'active',
                            'starts_at' => null,
                            'ends_at' => null,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        });
    }


    /**
     * Create or update a predefined demo user.
     * Reset the password from DEMO_SEED_PASSWORD on every seeder run.
     */
    private function createUser(array $data): User
    {
        $user = User::firstOrNew([
            'email' => $data['email'],
        ]);

        $user->account_type = $data['account_type'];
        $user->institution_id = $data['institution_id'];
        $user->name = $data['name'];
        $user->phone = $data['phone'];
        $user->status = 'active';

        // Reset password on every local demo seeding run.
        $user->password = Hash::make($this->seedPassword());

        $user->save();

        return $user;
    }


    private function seedPassword(): string
    {
        if ($this->resolvedSeedPassword !== null) {
            return $this->resolvedSeedPassword;
        }

        $configured = config('seeding.default_password');

        if (is_string($configured) && $configured !== '') {
            $this->resolvedSeedPassword = $configured;

            return $this->resolvedSeedPassword;
        }

        $this->resolvedSeedPassword = Str::random(24);

        $this->command?->warn(
            'SEED_USER_PASSWORD is not configured; a random password was generated.'
        );

        $this->command?->warn(
            'Generated password (save securely now): ' . $this->resolvedSeedPassword
        );

        $this->command?->warn(
            'Set SEED_USER_PASSWORD in .env for repeatable local seeding. Do not use shared seeding passwords for production accounts.'
        );

        return $this->resolvedSeedPassword;
    }
}
