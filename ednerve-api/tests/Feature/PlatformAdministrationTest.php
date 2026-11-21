<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platformAdmin = User::create([
            'account_type' => 'platform',
            'institution_id' => null,
            'name' => 'Platform Administrator',
            'email' => 'platform@example.test',
            'password' => Hash::make('StrongPass@123'),
            'status' => 'active',
        ]);

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlatformSettingsSeeder']);
    }

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken];
    }

    public function test_platform_admin_can_create_a_platform_user(): void
    {
        $response = $this->withHeaders($this->authHeader($this->platformAdmin))
            ->postJson('/api/platform/platform-users', [
                'name' => 'Second Admin',
                'email' => 'second-admin@example.test',
                'password' => 'StrongPass@123',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'second-admin@example.test',
            'account_type' => 'platform',
        ]);

        // And that the new admin can log in.
        $this->postJson('/api/auth/login', [
            'email' => 'second-admin@example.test',
            'password' => 'StrongPass@123',
        ])->assertOk();
    }

    public function test_platform_admin_cannot_change_own_status(): void
    {
        $response = $this->withHeaders($this->authHeader($this->platformAdmin))
            ->patchJson("/api/platform/users/{$this->platformAdmin->id}/status", [
                'status' => 'inactive',
            ]);

        $response->assertStatus(422);
    }

    public function test_last_platform_admin_cannot_be_deactivated(): void
    {
        $this->withHeaders($this->authHeader($this->platformAdmin))
            ->patchJson("/api/platform/users/{$this->platformAdmin->id}/status", [
                'status' => 'inactive',
            ])->assertStatus(422);

        $second = User::create([
            'account_type' => 'platform',
            'institution_id' => null,
            'name' => 'Second Admin',
            'email' => 'second@example.test',
            'password' => Hash::make('StrongPass@123'),
            'status' => 'active',
        ]);

        // Deactivating one of two platform admins is fine.
        $this->withHeaders($this->authHeader($this->platformAdmin))
            ->patchJson("/api/platform/users/{$second->id}/status", [
                'status' => 'inactive',
            ])->assertOk();
    }

    public function test_settings_roundtrip_boolean_and_type_guard(): void
    {
        $header = $this->authHeader($this->platformAdmin);

        $this->withHeaders($header)
            ->putJson('/api/platform/settings', [
                'settings' => [
                    ['key' => 'features.digital_transcripts_enabled', 'value' => true],
                ],
            ])->assertOk();

        $this->assertTrue(
            PlatformSetting::where('key', 'features.digital_transcripts_enabled')->first()->typedValue()
        );

        // Wrong type must be rejected.
        $this->withHeaders($header)
            ->putJson('/api/platform/settings', [
                'settings' => [
                    ['key' => 'features.digital_transcripts_enabled', 'value' => 'not_a_boolean'],
                ],
            ])->assertStatus(422);

        // Unknown keys must be rejected.
        $this->withHeaders($header)
            ->putJson('/api/platform/settings', [
                'settings' => [
                    ['key' => 'features.nonexistent_key', 'value' => true],
                ],
            ])->assertStatus(422);

        $this->withHeaders($header)
            ->putJson('/api/platform/settings', [
                'settings' => [
                    ['key' => 'features.digital_transcripts_enabled', 'value' => false],
                ],
            ])->assertOk();
    }

    public function test_custom_role_crud_and_permission_sync(): void
    {
        $header = $this->authHeader($this->platformAdmin);

        $create = $this->withHeaders($header)
            ->postJson('/api/platform/roles', [
                'code' => 'qa_auditor',
                'name' => 'QA Auditor',
                'description' => 'Verification role',
            ]);

        $create->assertStatus(201);

        $roleId = $create->json('role.id');

        $permissionIds = Permission::where('is_system', true)->pluck('id')->take(2);

        // Sync a subset of permissions.
        $this->withHeaders($header)
            ->putJson("/api/platform/roles/{$roleId}/permissions", [
                'permission_ids' => $permissionIds->all(),
            ])->assertOk();

        $role = Role::with('permissions')->find($roleId);

        $this->assertCount(2, $role->permissions);

        // System roles cannot be deleted.
        $systemRole = Role::create([
            'code' => 'system_qa_test',
            'name' => 'System QA',
            'status' => 'active',
            'is_system' => true,
        ]);

        $this->withHeaders($header)
            ->deleteJson("/api/platform/roles/{$systemRole->id}")
            ->assertStatus(422);

        // Custom role with no assignments can be deleted.
        $this->withHeaders($header)
            ->deleteJson("/api/platform/roles/{$roleId}")
            ->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_audit_events_recorded_for_platform_user_management(): void
    {
        $header = $this->authHeader($this->platformAdmin);

        $this->withHeaders($header)
            ->postJson('/api/platform/platform-users', [
                'name' => 'Audit Target',
                'email' => 'audit-target@example.test',
                'password' => 'StrongPass@123',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'platform_user.created',
            'module' => 'platform',
        ]);

        $audit = AuditLog::where('event', 'platform_user.created')->first();
        $this->assertNotNull($audit->actor_user_id);
    }
}
