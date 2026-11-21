<?php

namespace App\Services;

use App\Models\Credential;
use App\Models\Exam;
use App\Models\StudentProfile;
use App\Models\Result;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues and verifies W3C Verifiable-Credentials-compatible academic
 * credentials signed with the institution's ECDSA key.
 *
 * Payload keys are sorted recursively before signing so verifiers don't need
 * any special library — just a canonical JSON encoder.
 */
class CredentialService
{
    public function __construct(
        private readonly CredentialKeyService $keys,
        private readonly AuditService $audit,
    ) {}

    /**
     * Canonicalize: sort array keys recursively (depth-safe) then encode.
     */
    public static function canonicalise(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $isList = array_values($value) === $value;
        if (!$isList) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalise($item);
        }

        return $value;
    }

    public static function canonicalJson(array $payload): string
    {
        return json_encode(self::canonicalise($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ?: '';
    }

    /**
     * Issue a marksheet credential for one student from a published exam.
     */
    public function issueMarksheet(Exam $exam, StudentProfile $student): Credential
    {
        return DB::transaction(function () use ($exam, $student) {
            $institution = $exam->institution()->first(['id', 'code', 'name']);

            $result = Result::where('exam_id', $exam->id)
                ->where('student_profile_id', $student->id)
                ->first();

            $subjectGrades = DB::table('marks')
                ->join('exam_components', 'exam_components.id', '=', 'marks.exam_component_id')
                ->join('exam_subjects', 'exam_subjects.id', '=', 'exam_components.exam_subject_id')
                ->join('subjects', 'subjects.id', '=', 'exam_subjects.subject_id')
                ->where('exam_subjects.exam_id', $exam->id)
                ->where('marks.student_profile_id', $student->id)
                ->orderBy('subjects.code')
                ->get(['subjects.code as code', 'subjects.name as name', 'marks.marks_obtained', 'marks.status as mark_status', 'exam_components.max_marks'])
                ->groupBy('code');

            $results = [];
            foreach ($subjectGrades as $code => $rows) {
                $obtained = $rows->where('mark_status', 'present')->sum('marks_obtained');
                $outOf = (float) $rows->sum('max_marks');
                $percent = $outOf > 0 ? round($obtained / $outOf * 100, 1) : 0.0;
                $results[] = [
                    'subjectCode' => $code,
                    'percent' => $percent,
                    'pass' => $result?->result_status === 'pass',
                ];
            }
            usort($results, fn ($a, $b) => $a['subjectCode'] <=> $b['subjectCode']);

            $issuedAt = now();
            $studentData = $student->load('user:id,name,email', 'programme:id,code,name', 'academicSession:id,code,name');

            $payload = [
                '@context' => ['https://www.w3.org/2018/credentials/v1'],
                'type' => ['VerifiableCredential', 'AcademicMarksheetCredential'],
                'id' => 'urn:uuid:' . Str::uuid()->toString(),
                'issuer' => [
                    'id' => env('VC_ISSUER_ID', rtrim(config('app.url'), '/')),
                    'name' => $institution->name ?? 'Unknown Institution',
                ],
                'issuanceDate' => $issuedAt->toIso8601String(),
                'credentialSubject' => [
                    'id' => 'urn:student:' . ($studentData->application_number ?? $student->id),
                    'applicationNumber' => $studentData->application_number,
                    'name' => $studentData->user?->name,
                    'programme' => $studentData->programme?->code,
                    'programmeName' => $studentData->programme?->name,
                    'academicSession' => $studentData->academicSession?->code,
                    'examTitle' => $exam->title,
                    'totalPercentage' => (float) ($result?->percentage ?? 0),
                    'overallGrade' => $result?->grade,
                    'overallStatus' => $result?->result_status,
                    'subjectResults' => $results,
                ],
            ];

            $payloadJson = self::canonicalJson($payload);
            $signature = $this->sign($payloadJson);

            $pub = $this->keys->publicKeyPem();
            $publicKeyId = 'ecdsa-p256-' . substr(hash('sha256', $pub), 0, 12);

            $credentialNumber = $this->nextCredentialNumber($exam, $student);

            return Credential::create([
                'institution_id' => $exam->institution_id,
                'credential_number' => $credentialNumber,
                'student_profile_id' => $student->id,
                'type' => 'marksheet',
                'exam_id' => $exam->id,
                'programme_id' => $student->programme_id,
                'academic_session_id' => $student->academic_session_id,
                'payload' => json_decode($payloadJson, true),
                'signature' => $signature,
                'signature_algorithm' => 'EcdsaSecp256r1Signature2019',
                'public_key_id' => $publicKeyId,
                'status' => 'issued',
                'issued_at' => $issuedAt,
                'expires_at' => null,
            ]);
        });
    }

    public function sign(string $payloadJson): string
    {
        $private = $this->keys->privateKeyPem();
        $signature = '';

        if (openssl_sign($payloadJson, $signature, $private, OPENSSL_ALGO_SHA256) !== true) {
            throw new \RuntimeException('Credential signing failed: ' . openssl_error_string());
        }

        return base64_encode($signature);
    }

    public function verifySignature(array $payload, string $signatureBase64): bool
    {
        $public = $this->keys->publicKeyPem();
        $payloadJson = self::canonicalJson($payload);

        $ok = openssl_verify($payloadJson, base64_decode($signatureBase64), $public, OPENSSL_ALGO_SHA256);

        return $ok === 1;
    }

    private function nextCredentialNumber(Exam $exam, StudentProfile $student): string
    {
        $year = now()->year;
        $count = Credential::where('institution_id', $exam->institution_id)
            ->whereYear('issued_at', $year)
            ->count() + 1;

        return strtoupper($exam->institution()->first(['code'])?->code ?? 'EXM')
            . '-' . $year . '-'
            . str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }
}
