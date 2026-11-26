<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use App\Services\AccountActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountActivationTest extends TestCase
{
    use RefreshDatabase;

    private Institution $institution;

    private function pendingUser(): User
    {
        return User::create([
            'account_type' => 'institution',
            'institution_id' => $this->institution->id,
            'name' => 'Activation Test Student',
            'email' => 'activation@example.test',
            'password' => str()->random(40),
            'status' => 'pending_activation',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create([
            'code' => 'ACT',
            'name' => 'Activation Test Institution',
            'slug' => 'activation-test',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);
    }

    public function test_valid_token_activates_account_and_sets_password(): void
    {
        $user = $this->pendingUser();
        $token = app(AccountActivationService::class)->issue($user);

        $response = $this->postJson('/api/auth/password/setup', [
            'token' => $token,
            'password' => 'NewStrongPass@123',
            'password_confirmation' => 'NewStrongPass@123',
        ]);

        $response->assertOk();

        $user->refresh();

        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('NewStrongPass@123', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->pendingUser();
        $token = app(AccountActivationService::class)->issue($user);

        DB::table('user_activation_tokens')
            ->where('user_id', $user->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/auth/password/setup', [
            'token' => $token,
            'password' => 'NewStrongPass@123',
            'password_confirmation' => 'NewStrongPass@123',
        ])->assertUnprocessable();

        $this->assertSame('pending_activation', $user->fresh()->status);
    }

    public function test_activation_token_cannot_be_reused(): void
    {
        $user = $this->pendingUser();
        $token = app(AccountActivationService::class)->issue($user);

        $payload = [
            'token' => $token,
            'password' => 'NewStrongPass@123',
            'password_confirmation' => 'NewStrongPass@123',
        ];

        $this->postJson('/api/auth/password/setup', $payload)->assertOk();
        $this->postJson('/api/auth/password/setup', $payload)->assertUnprocessable();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->pendingUser();

        $this->postJson('/api/auth/password/setup', [
            'token' => str()->random(64),
            'password' => 'NewStrongPass@123',
            'password_confirmation' => 'NewStrongPass@123',
        ])->assertUnprocessable();
    }

    public function test_pending_account_cannot_login_but_can_login_after_activation(): void
    {
        $user = $this->pendingUser();

        $token = app(AccountActivationService::class)->issue($user);

        $credentials = [
            'email' => $user->email,
            'password' => 'NewStrongPass@123',
            'device_name' => 'Activation Test',
        ];

        // Pending account must not authenticate.
        $this->postJson('/api/auth/login', $credentials)
            ->assertUnprocessable();

        // Activate and set the password.
        $this->postJson('/api/auth/password/setup', [
            'token' => $token,
            'password' => $credentials['password'],
            'password_confirmation' => $credentials['password'],
        ])->assertOk();

        // Active account can now authenticate.
        $this->postJson('/api/auth/login', $credentials)
            ->assertOk();
    }
}
