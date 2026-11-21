<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OWASP Secure Headers for every API response.
 *
 *  - HSTS: forces TLS for one year on the API host.
 *  - CSP: API JSON only — no scripts allowed to run.
 *  - X-Content-Type-Options / X-Frame-Options / Referrer-Policy: standard hardening.
 */
class ApiSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
