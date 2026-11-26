# OolaEdNerve — Applicant Login & Authentication

## Scope

This patch adds a separate applicant authentication flow using the existing `admission_applicants` identity table and Laravel Sanctum personal access tokens. No database migration is required: Sanctum's existing `personal_access_tokens` table supports polymorphic token owners.

## Authentication separation

- `AdmissionApplicant` now extends Laravel's `Authenticatable` and uses Sanctum's `HasApiTokens`.
- Applicant login issues a Sanctum token whose `tokenable_type` is `App\\Models\\AdmissionApplicant`, with the `applicant` ability and a 30-day expiry.
- Applicant routes require both `auth:sanctum` and the `applicant` middleware. The middleware verifies the authenticated model type, token ability, active applicant status, verified email, and active institution.
- Existing institutional/platform routes now also require `institution.account`, which accepts only `App\\Models\\User`. This prevents an applicant token from entering institutional APIs while preserving both platform and institution accounts.
- Institutional `User` model, login endpoints, token creation, AuthContext, and existing institutional route handlers are otherwise unchanged.

## Endpoints

| Method | Endpoint | Authentication |
|---|---|---|
| POST | `/api/public/applicants/login` | Public; rate-limited |
| GET | `/api/applicants/auth/me` | Applicant bearer token |
| POST | `/api/applicants/auth/logout` | Applicant bearer token |

### Login request

```json
{
  "institution_code": "AMU",
  "email": "applicant@example.com",
  "password": "your-password",
  "device_name": "web-browser"
}
```

`device_name` is optional. Institution code or slug is resolved against active institutions. Email is normalized to lowercase and matched only within that institution. Login requires `email_verified_at` and `status = active`.

### Login response

Returns `access_token`, `token_type`, `expires_at`, and a safe applicant profile with institution details. The password and token hash are never returned.

## Rate limiting

Applicant login: 5 attempts per minute per institution code, normalized email, and IP address.

## Files in this patch

- `app/Models/AdmissionApplicant.php`
- `app/Http/Controllers/Api/ApplicantAuthController.php`
- `app/Http/Middleware/EnsureAdmissionApplicant.php`
- `app/Http/Middleware/EnsureInstitutionAccount.php`
- `app/Providers/AppServiceProvider.php`
- `bootstrap/app.php`
- `routes/api.php`
- `tests/Feature/ApplicantAuthenticationTest.php`

## Apply and test

1. Back up the API project.
2. Extract this patch into the Laravel API project, preserving folder structure and overwriting the listed files.
3. Run:

```bash
php artisan route:list --path=applicants
php artisan test --filter=ApplicantAuthenticationTest
php artisan test
```

No `migrate:fresh` or migration command is needed for this patch.

## Verification status

PHP syntax validation was run on the patch files. PHPUnit was not run in the patch-generation environment because the uploaded source archive did not contain the Composer `vendor` directory. Run the test commands above in the user's configured project before treating the batch as verified.
