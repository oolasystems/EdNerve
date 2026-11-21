<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Role;
use App\Models\SecurityAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass@123';

    private Institution $institution;
    private int $adminRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create([
            'code' => 'SVCI',
            'name' => 'Security Monitoring Test College',
            'slug' => 'svci',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->adminRoleId = Role::create(['code' => 'institution_admin', 'name' => 'Institution Admin', 'status' => 'active'])->id;
    }

    private function makeUser(string $email, int $roleId): User
    {
        $user = User::create([
            'account_type' => 'institution',
            'institution_id' => $this->institution->id,
            'name' => $email,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
        ]);

        \DB::table('user_role_assignments')->insert([
            'institution_id' => $this->institution->id,
            'user_id' => $user->id,
            'role_id' => $roleId,
            'academic_unit_id' => null,
            'is_primary' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_repeated_failed_logins_trigger_alert(): void
    {
        $email = 'victim@svci.example.com';
        $user = $this->makeUser($email, $this->adminRoleId);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'wrong-password'])
                ->assertStatus(422);
        }
        $this->assertDatabaseMissing('security_alerts', ['kind' => 'auth.repeated_failures']);

        // 5th failure crosses the threshold
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'wrong-password'])
            ->assertStatus(422);

        $this->assertDatabaseHas('security_alerts', ['kind' => 'auth.repeated_failures']);
    }

    public function test_chain_tamper_creates_critical_alert_on_verify(): void
    {
        $admin = $this->makeUser('admin.svci.test', $this->adminRoleId);
        Sanctum::actingAs($admin);

        // Create an examination to generate audit entries.
        $programme = \App\Models\Programme::create([
            'institution_id' => $this->institution->id,
            'code' => 'MCA',
            'name' => 'MCA',
            'level' => 'PG',
            'duration_semesters' => 4,
            'status' => 'active',
        ]);
        \App\Models\Subject::create([
            'institution_id' => $this->institution->id,
            'code' => 'MC101',
            'name' => 'Software Engineering',
            'credits' => 4,
            'subject_type' => 'theory',
            'status' => 'active',
        ]);

        $this->postJson('/api/examinations', [
            'title' => 'Chain Test Exam',
            'kind' => 'class_test',
            'programme_id' => $programme->id,
            'semester' => 1,
        ])->assertCreated();

        $row = DB::table('audit_logs')->orderByDesc('id')->first();
        $this->assertNotNull($row, 'expected at least one audit entry');

        DB::table('audit_logs')->where('id', $row->id)->update([
            'new_values' => json_encode(['probe' => 'tampered']),
        ]);

        $this->getJson('/api/security/audit/verify')->assertOk();

        $this->assertDatabaseHas('security_alerts', ['kind' => 'audit.chain_broken']);
    }

    public function test_admin_can_list_and_acknowledge_own_alerts(): void
    {
        $admin = $this->makeUser('admin.svci.test', $this->adminRoleId);

        $alert = SecurityAlert::create([
            'institution_id' => $this->institution->id,
            'kind' => 'auth.repeated_failures',
            'severity' => 'high',
            'message' => 'Seeded alert',
            'context' => ['email' => 'whatever@x.test'],
            'ip_address' => '127.0.0.1',
        ]);

        Sanctum::actingAs($admin);
        $list = $this->getJson('/api/security/alerts')->assertOk()->json('alerts');
        $this->assertCount(1, $list);
        $this->assertSame($alert->id, $list[0]['id']);
        $this->assertSame(1, $this->getJson('/api/security/alerts')->json('unacknowledged_count'));

        $this->patchJson("/api/security/alerts/{$alert->id}/ack")->assertOk();
        $this->assertSame(0, $this->getJson('/api/security/alerts')->json('unacknowledged_count'));
    }

    public function test_student_cannot_list_alerts(): void
    {
        $studentRoleId = Role::create(['code' => 'student', 'name' => 'Student', 'status' => 'active'])->id;
        $student = $this->makeUser('student.svci.test', $studentRoleId);

        Sanctum::actingAs($student);
        $this->getJson('/api/security/alerts')->assertForbidden();
        $this->patchJson('/api/security/alerts/1/ack')->assertForbidden();
    }
}
