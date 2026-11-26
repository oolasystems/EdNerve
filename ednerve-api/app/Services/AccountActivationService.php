<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivationToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountActivationService
{
    private const TOKEN_EXPIRY_HOURS = 24;

    /**
     * Issue a new activation token.
     * Return the raw token only to the caller for delivery.
     */
    public function issue(User $user): string
    {
        return DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($user->status !== 'pending_activation') {
                throw ValidationException::withMessages([
                    'account' => ['This account does not require activation.'],
                ]);
            }

            UserActivationToken::where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            $token = Str::random(64);

            UserActivationToken::create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(self::TOKEN_EXPIRY_HOURS),
            ]);

            return $token;
        });
    }

    /**
     * Consume a valid activation token and activate the account.
     */
    public function activate(string $token, string $password): User
    {
        return DB::transaction(function () use ($token, $password) {
            $activation = UserActivationToken::where(
                'token_hash',
                hash('sha256', $token)
            )->lockForUpdate()->first();

            if (
                !$activation ||
                $activation->used_at !== null ||
                $activation->expires_at->isPast()
            ) {
                throw ValidationException::withMessages([
                    'token' => ['This activation link is invalid or has expired.'],
                ]);
            }

            $user = User::whereKey($activation->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($user->status !== 'pending_activation') {
                throw ValidationException::withMessages([
                    'token' => ['This activation link is no longer valid.'],
                ]);
            }

            $user->update([
                'password' => $password,
                'status' => 'active',
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            $activation->update(['used_at' => now()]);

            // Invalidate any other outstanding activation tokens.
            UserActivationToken::where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            return $user;
        });
    }
}
