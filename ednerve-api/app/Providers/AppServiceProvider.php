<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Throttle brute-force login attempts: 5 attempts per minute
         * per (email, IP) pair. Independent of per-session quotas.
         */
        RateLimiter::for('auth-login', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));
            $key = $email . '|' . $request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Public applicant portal: generous for humans, hostile to bots.
        RateLimiter::for('public-apply', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Send password-reset links to the React application, not the API host.
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $frontendUrl = rtrim(
                (string) config('app.frontend_url', 'http://localhost:5173'),
                '/'
            );

            return $frontendUrl.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });
    }
}
