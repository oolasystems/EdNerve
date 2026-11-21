<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Credential;
use App\Models\StudentProfile;
use App\Models\CredentialVerification;
use App\Models\Programme;
use App\Services\CredentialKeyService;
use App\Services\CredentialService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CredentialController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit,
        private readonly CredentialService $credentials,
        private readonly CredentialKeyService $keys,
    ) {}

    /* =========================
     |  Admin — list, issue, revoke
     |========================= */

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user?->isInstitutionAdmin() || $user?->hasRole('examination_officer') || $user?->hasRole('hod') || $user?->hasRole('dean'),
            403,
            'Credential access required.'
        );

        $institutionId = $user->institution_id;

        $query = Credential::where('institution_id', $institutionId)
            ->with(['studentProfile.user:id,name,email', 'exam:id,title,status', 'programme:id,code,name'])
            ->orderByDesc('issued_at');

        if ($examId = $request->query('exam_id')) {
            $query->where('exam_id', (int) $examId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        $paginator = $query->paginate($perPage);

        return response()->json($paginator);
    }

    public function issueMarksheets(Request $request, int $examId)
    {
        $user = $request->user();
        abort_unless(
            $user?->isInstitutionAdmin() || $user?->hasRole('examination_officer'),
            403,
            'Institution administrator or examination officer required.'
        );

        $institutionId = $user->institution_id;
        $exam = Exam::where('institution_id', $institutionId)->findOrFail($examId);

        abort_unless($exam->status === 'published', 409, 'Results must be published before issuing credentials.');

        $students = StudentProfile::where('institution_id', $institutionId)
            ->where('programme_id', $exam->programme_id)
            ->where('enrollment_status', 'current')
            ->with('user:id,name,email', 'programme:id,code', 'academicSession:id,code')
            ->get();

        $count = 0;
        foreach ($students as $student) {
            // Idempotent: one credential per exam per student.
            if (Credential::where('exam_id', $exam->id)->where('student_profile_id', $student->id)->exists()) {
                continue;
            }
            $this->credentials->issueMarksheet($exam, $student);
            $count++;
        }

        $this->audit->record(
            $request,
            event: 'credentials.issued',
            module: 'institution',
            subject: $exam,
            newValues: ['exam_id' => $exam->id, 'count' => $count],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => "{$count} credential(s) issued.",
            'count' => $count,
        ], 201);
    }

    public function revoke(Request $request, int $id)
    {
        $user = $request->user();
        abort_unless(
            $user?->isInstitutionAdmin() || $user?->hasRole('examination_officer'),
            403,
            'Institution administrator or examination officer required.'
        );

        $institutionId = $user->institution_id;
        $credential = Credential::where('institution_id', $institutionId)->findOrFail($id);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $credential->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoke_reason' => $validated['reason'],
        ]);

        $this->audit->record(
            $request,
            event: 'credentials.revoked',
            module: 'institution',
            subject: $credential->fresh(),
            newValues: ['reason' => $validated['reason']],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Credential revoked.',
            'credential' => [
                'id' => $credential->id,
                'credential_number' => $credential->credential_number,
                'status' => $credential->fresh()->status,
            ],
        ]);
    }

    /* =========================
     |  Public — verify (no auth)
     |========================= */

    public function verify(Request $request, string $credentialNumber)
    {
        $institutionId = null; // global verification — no institution scope

        $credential = Credential::where('credential_number', $credentialNumber)->first();

        if (!$credential) {
            $this->recordVerification(null, $credentialNumber, 'not_found', $request);

            return response()->json([
                'valid' => false,
                'status' => 'not_found',
                'message' => 'No credential with that number exists.',
            ], 404);
        }

        $signatureValid = $this->credentials->verifySignature(
            $credential->payload,
            $credential->signature
        );

        $status = 'valid';
        if ($credential->status === 'revoked') {
            $status = 'revoked';
        } elseif ($credential->expires_at && $credential->expires_at->isPast()) {
            $status = 'expired';
        } elseif (!$signatureValid) {
            $status = 'tampered';
        }

        $this->recordVerification($credential, $credentialNumber, $status === 'valid' ? 'valid' : $status, $request);

        return response()->json([
            'valid' => $status === 'valid',
            'status' => $status,
            'credential_number' => $credential->credential_number,
            'type' => $credential->type,
            'issued_at' => $credential->issued_at,
            'revoked_at' => $credential->revoked_at,
            'revocation_reason' => $credential->revoke_reason,
            'institution' => [
                'name' => $credential->institution?->name,
                'code' => $credential->institution?->code,
            ],
            'student' => [
                'name' => $credential->studentProfile?->user?->name,
                'application_number' => $credential->studentProfile?->application_number,
            ],
            'programme' => $credential->programme?->code,
            'academic_session' => $credential->academicSession?->code,
            'results' => $credential->payload['credentialSubject'] ?? null,
            'signature_valid' => $signatureValid,
        ]);
    }

    private function recordVerification(?Credential $credential, string $credentialNumber, string $result, Request $request): void
    {
        CredentialVerification::create([
            'credential_id' => $credential?->id,
            'credential_number' => $credentialNumber,
            'result' => $result,
            'visitor_ip' => $request->ip() ?? 'unknown',
            'user_agent' => Str::limit((string) $request->userAgent(), 255),
        ]);
    }

    /* =========================
     |  Public — well-known keys (no auth)
     |========================= */

    public function verificationKeys()
    {
        $entries = [];

        $entries[] = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'alg' => 'ES256',
            'kid' => 'ecdsa-p256-' . substr(hash('sha256', $this->keys->publicKeyPem()), 0, 12),
            'x5u' => url('/.well-known/verification-keys.json'), // placeholder pointer; same file
            'public_key_pem' => $this->keys->publicKeyPem(),
            'fingerprint' => $this->keys->publicKeyFingerprint(),
            'status' => 'active',
        ];

        return response()->json([
            'keys' => $entries,
        ]);
    }
}
