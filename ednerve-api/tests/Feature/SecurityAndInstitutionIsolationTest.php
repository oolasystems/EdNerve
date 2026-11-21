<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityAndInstitutionIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass@123';

    private Institution $institutionA;
    private Institution $institutionB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institutionA = Institution::create([
            'code' => 'TSTA',
            'name' => 'Institution A',
            'slug' => 'tsta',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->institutionB = Institution::create([
            'code' => 'TSTB',
            'name' => 'Institution B',
            'slug' => 'tstb',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        Role::create([
            'code' => 'institution_admin',
            'name' => 'Institution Admin',
            'status' => 'active',
        ]);
    }

    private function makeUser(
        string $accountType,
        ?Institution $institution,
        string $email,
        string $status = 'active'
    ): User {
        return User::create([
            'account_type' => $accountType,
            'institution_id' => $institution?->id,
            'name' => $email,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'status' => $status,
        ]);
    }

    public function test_health_endpoint_is_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_login_rejects_invalid_credentials_with_uniform_error(): void
    {
        $this->makeUser('platform', null, 'platform@example.test');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'platform@example.test',
            'password' => 'WrongPassword@1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Invalid email or password.');
    }

    public function test_platform_admin_can_login(): void
    {
        $this->makeUser('platform', null, 'platform@example.test');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'platform@example.test',
            'password' => self::PASSWORD,
        ]);

        $response->assertOk()
            ->assertJsonPath('user.account_type', 'platform');

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_successful_login_writes_an_audit_log(): void
    {
        $this->makeUser('platform', null, 'platform@example.test');

        $this->postJson('/api/auth/login', [
            'email' => 'platform@example.test',
            'password' => self::PASSWORD,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.login_succeeded',
            'module' => 'auth',
        ]);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $this->makeUser('platform', null, 'platform@example.test');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'platform@example.test',
                'password' => 'WrongPassword@1',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'platform@example.test',
            'password' => 'WrongPassword@1',
        ])->assertStatus(429);
    }

    public function test_institution_admin_cannot_access_platform_routes(): void
    {
        $institutionAdmin = $this->makeUser(
            'institution',
            $this->institutionA,
            'inst-admin@example.test'
        );

        Sanctum::actingAs($institutionAdmin, ['*']);

        $this->getJson('/api/platform/institutions')->assertStatus(403);
    }

    public function test_institution_user_lists_only_own_institution_users(): void
    {
        $adminA = $this->makeUser('institution', $this->institutionA, 'admin-a@example.test');
        $this->makeUser('institution', $this->institutionA, 'user-a@example.test');
        $this->makeUser('institution', $this->institutionB, 'user-b@example.test');

        Sanctum::actingAs($adminA, ['*']);

        $response = $this->getJson('/api/users');

        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email')->all();

        $this->assertContains('admin-a@example.test', $emails);
        $this->assertContains('user-a@example.test', $emails);
        $this->assertNotContains('user-b@example.test', $emails);
    }

    public function test_institution_assignment_rejects_cross_institution_organization_unit(): void
    {
        $adminA = $this->makeUser('institution', $this->institutionA, 'admin-a@example.test');

        $role = Role::where('code', 'institution_admin')->first();

        $adminA->roleAssignments()->create([
            'institution_id' => $this->institutionA->id,
            'role_id' => $role->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        // Cross-institution unit belongs to institution B; the API must refuse it.
        $unitB = \App\Models\AcademicUnit::create([
            'institution_id' => $this->institutionB->id,
            'code' => 'B-CSE',
            'name' => 'B CSE',
            'unit_type' => 'department',
            'status' => 'active',
        ]);

        $token = $adminA->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/role-assignments', [
                'user_id' => $adminA->id,
                'role_id' => $role->id,
                'academic_unit_id' => $unitB->id,
            ]);

        $response->assertStatus(404); // findOrFail on the institution-scoped query
    }
}
