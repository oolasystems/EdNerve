# Applicant Registration & Email Verification API

Canonical identity model: `AdmissionApplicant` (`admission_applicants`). The older `Applicant` model/table is not used by this implementation.

## Endpoints

All endpoints are public and rate-limited:

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/public/applicants/register` | Register an applicant and send verification email |
| POST | `/api/public/applicants/verify-email` | Verify the email address and issue a short-lived password-setup token |
| POST | `/api/public/applicants/resend-verification` | Resend verification email for an unverified applicant |
| POST | `/api/public/applicants/set-password` | Set the password using the one-time password-setup token |

### Register

```json
{
  "institution_code": "AMU",
  "full_name": "Aarav Sharma",
  "email": "aarav@example.com",
  "mobile_country_code": "+91",
  "mobile": "9876543210"
}
```

Returns HTTP 202 with a generic message to avoid disclosing whether an email is already registered.

### Verify email

```json
{
  "institution_code": "AMU",
  "email": "aarav@example.com",
  "token": "<64-character-token-from-email-link>"
}
```

Returns a `setup_token` to the client after successful verification. The setup token is stored only as a SHA-256 hash and expires after 30 minutes.

### Set password

```json
{
  "institution_code": "AMU",
  "email": "aarav@example.com",
  "setup_token": "<setup-token-from-verification-response>",
  "password": "A_Strong_Password_123",
  "password_confirmation": "A_Strong_Password_123"
}
```

Password must be at least 12 characters. The setup token is single-use.

## Security behavior

- Email uniqueness is institution-scoped using `normalized_email`.
- Only active institutions can register applicants.
- Verification token is random, hashed before persistence, expires after 24 hours, and is single-use.
- At most five incorrect verification attempts are accepted for the current token.
- Resending invalidates prior unconsumed email-verification tokens.
- Password setup requires a separate, one-time, 30-minute token returned only after successful email verification. Knowing an email address alone is insufficient.
- Applicant identity remains separate from institutional `users` accounts.
- Existing institutional authentication and anonymous public application submission routes are unchanged.

## Configuration

The verification email links to `FRONTEND_URL` (Laravel config key `app.frontend_url`) at `/applicant/verify-email` with `institution_code`, `email`, and `token` query parameters. Configure a real mail transport before production use. `.env` is intentionally excluded from the source package.

## Verification

Added feature tests in `tests/Feature/ApplicantRegistrationTest.php` for registration, token-hash persistence, institution-scoped duplicate handling, email verification, single-use behavior, password setup, and institution isolation.

Run in the project environment:

```bash
php artisan test --filter=ApplicantRegistrationTest
php artisan test
php artisan route:list --path=public/applicants
```

The source ZIP supplied for this change did not contain `vendor/`, so PHPUnit could not be executed in the packaging environment. PHP syntax checks passed for every changed PHP file.
