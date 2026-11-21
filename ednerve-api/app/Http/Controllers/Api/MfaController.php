<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMfaSecret;
use App\Services\AuditService;
use App\Services\AuthSessionService;
use App\Services\MfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MfaController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly MfaService $mfa,
        private readonly AuthSessionService $sessions,
        private readonly AuditService $audit
    ) {}

    /* =========================
     |  Status
     |========================= */

    public function status(Request $request)
    {
        $user = $request->user();
        $secret = $user->mfaSecret;

        return response()->json([
            'enrolled' => (bool) $secret,
            'confirmed' => (bool) $secret?->confirmed_at,
            'recovery_codes_remaining' => DB::table('mfa_recovery_codes')
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->count(),
            'required' => $user->requiresPrivilegedMfa(),
        ]);
    }

    /* =========================
     |  Enrolment
     |========================= */

    public function enrol(Request $request)
    {
        $user = $request->user();
        $existing = $user->mfaSecret;

        if ($existing && $existing->confirmed_at) {
            return response()->json(['message' => 'MFA is already enabled.'], 409);
        }

        $secret = $this->mfa->generateSecret();

        // Paranoia: keep the encrypted blob only; never log the plaintext.
        $encrypted = $this->mfa->encryptSecret($secret);

        if ($existing) {
            $existing->update(['secret' => $encrypted, 'confirmed_at' => null]);
        } else {
            UserMfaSecret::create([
                'user_id' => $user->id,
                'secret' => $encrypted,
                'confirmed_at' => null,
            ]);
        }

        $this->audit->record(
            $request,
            event: 'mfa.enrolment_started',
            module: 'auth',
            subject: $user,
            institutionId: $user->institution_id,
            actorUserId: $user->id,
        );

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => $this->mfa->otpauthUri(
                $secret,
                'OolaEdNerve',
                $user->email
            ),
        ]);
    }

    public function confirm(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:6'],
        ]);

        $secret = $user->mfaSecret;
        abort_unless($secret, 422, 'Start enrolment first.');
        abort_if($secret->confirmed_at, 409, 'MFA is already enabled.');

        $plaintext = $this->mfa->decryptSecret($secret->secret);
        abort_unless($this->mfa->verifyTotp($plaintext, $validated['code']), 422, 'Invalid code — check the app clock and try again.');

        $recoveryCodes = $this->mfa->generateRecoveryCodes(8);

        DB::transaction(function () use ($secret, $user, $recoveryCodes) {
            $secret->update(['confirmed_at' => now()]);
            DB::table('mfa_recovery_codes')->where('user_id', $user->id)->delete();
            foreach ($recoveryCodes as $code) {
                DB::table('mfa_recovery_codes')->insert([
                    'user_id' => $user->id,
                    'code_hash' => Hash::make($code),
                    'used_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->audit->record(
            $request,
            event: 'mfa.enrolment_confirmed',
            module: 'auth',
            subject: $user,
            institutionId: $user->institution_id,
            actorUserId: $user->id,
        );

        return response()->json([
            'message' => 'MFA enabled. Store these recovery codes somewhere safe — they are shown once.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /* =========================
     |  Login challenge
     |========================= */

    public function challenge(Request $request)
    {
        $validated = $request->validate([
            'mfa_token' => ['required', 'string'],
            'code' => ['required', 'string', 'min:6', 'max:40'], // TOTP or recovery code
        ]);

        try {
            $payload = json_decode(Crypt::decryptString($validated['mfa_token']), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['mfa_token' => ['Invalid or expired MFA session. Sign in again.']]);
        }

        abort_unless(is_array($payload) && isset($payload['user_id'], $payload['exp']), 422, 'Invalid MFA session.');
        abort_if((int) $payload['exp'] < now()->timestamp, 422, 'MFA session expired. Sign in again.');

        $user = User::where('id', $payload['user_id'])->where('status', 'active')->first();
        abort_unless($user, 422, 'Account unavailable.');

        $secret = $user->mfaSecret;
        abort_unless($secret && $secret->confirmed_at, 422, 'MFA is not enabled for this account.');

        $code = trim($validated['code']);
        $plaintext = $this->mfa->decryptSecret($secret->secret);

        if ($this->mfa->verifyTotp($plaintext, $code)) {
            return response()->json(
                $this->sessions->issue($user, $request, 'mfa-challenge')
            );
        }

        // Try recovery codes (single use).
        $codes = DB::table('mfa_recovery_codes')
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->get();

        foreach ($codes as $row) {
            if (Hash::check($code, $row->code_hash)) {
                DB::table('mfa_recovery_codes')->where('id', $row->id)->update(['used_at' => now()]);

                $this->audit->record(
                    $request,
                    event: 'mfa.recovery_code_used',
                    module: 'auth',
                    subject: $user,
                    institutionId: $user->institution_id,
                    actorUserId: $user->id,
                );

                return response()->json(
                    $this->sessions->issue($user, $request, 'recovery-code')
                );
            }
        }

        $this->audit->record(
            $request,
            event: 'mfa.challenge_failed',
            module: 'auth',
            subject: $user,
            institutionId: $user->institution_id,
            actorUserId: $user->id,
        );

        throw ValidationException::withMessages(['code' => ['Invalid code.']]);
    }

    /* =========================
     |  Disable
     |========================= */

    public function disable(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:40'],
        ]);

        $secret = $user->mfaSecret;
        abort_unless($secret && $secret->confirmed_at, 422, 'MFA is not enabled.');

        $plaintext = $this->mfa->decryptSecret($secret->secret);
        $code = trim($validated['code']);

        $valid = $this->mfa->verifyTotp($plaintext, $code);

        if (!$valid) {
            // Allow a recovery code to authorize disable.
            $codes = DB::table('mfa_recovery_codes')
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->get();
            foreach ($codes as $row) {
                if (Hash::check($code, $row->code_hash)) {
                    $valid = true;
                    DB::table('mfa_recovery_codes')->where('id', $row->id)->update(['used_at' => now()]);
                    break;
                }
            }
        }

        abort_unless($valid, 422, 'Invalid code.');

        DB::transaction(function () use ($user) {
            UserMfaSecret::where('user_id', $user->id)->delete();
            DB::table('mfa_recovery_codes')->where('user_id', $user->id)->delete();
        });

        $this->audit->record(
            $request,
            event: 'mfa.disabled',
            module: 'auth',
            subject: $user,
            institutionId: $user->institution_id,
            actorUserId: $user->id,
        );

        return response()->json(['message' => 'MFA disabled.']);
    }

    /* =========================
     |  Sessions
     |========================= */

    public function tokens(Request $request)
    {
        $tokens = $request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at', 'expires_at'])
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
                'current' => $request->user()->currentAccessToken()?->id === $token->id,
            ]);

        return response()->json(['tokens' => $tokens]);
    }

    public function revokeToken(Request $request, int $tokenId)
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();
        abort_unless($deleted, 404, 'Session not found.');

        return response()->json(['message' => 'Session revoked.']);
    }

    public function revokeAllTokens(Request $request)
    {
        $currentId = $request->user()->currentAccessToken()?->id;
        $request->user()->tokens()->where('id', '!=', $currentId)->delete();

        return response()->json(['message' => 'Other sessions revoked.']);
    }
}
