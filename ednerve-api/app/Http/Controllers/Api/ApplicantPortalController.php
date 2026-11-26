<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplicant;
use App\Models\AdmissionIntake;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplicantPortalController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        return response()->json(['applicant' => $this->profilePayload($request->user())]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var AdmissionApplicant $applicant */
        $applicant = $request->user();
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:150'],
            'mobile_country_code' => ['nullable', 'string', 'max:8'],
            'mobile' => ['nullable', 'string', 'max:30'],
        ]);

        $mobileChanged = array_key_exists('mobile', $data)
            && ($data['mobile'] ?? null) !== $applicant->mobile;
        $countryChanged = array_key_exists('mobile_country_code', $data)
            && ($data['mobile_country_code'] ?? null) !== $applicant->mobile_country_code;

        $applicant->fill($data);
        if ($mobileChanged || $countryChanged) {
            $applicant->mobile_verified_at = null;
        }
        $applicant->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'applicant' => $this->profilePayload($applicant->fresh('institution')),
        ]);
    }

    public function intakes(Request $request): JsonResponse
    {
        /** @var AdmissionApplicant $applicant */
        $applicant = $request->user();
        $intakes = AdmissionIntake::query()
            ->with(['programme:id,institution_id,code,name,level,duration_semesters', 'academicSession:id,institution_id,name'])
            ->where('institution_id', $applicant->institution_id)
            ->where('status', 'open')
            ->whereDate('open_date', '<=', today())
            ->whereDate('close_date', '>=', today())
            ->whereHas('programme', fn ($q) => $q->where('status', 'active'))
            ->orderBy('close_date')
            ->get()
            ->map(fn (AdmissionIntake $intake) => [
                'id' => $intake->id,
                'name' => $intake->name,
                'open_date' => $intake->open_date?->toDateString(),
                'close_date' => $intake->close_date?->toDateString(),
                'application_fee' => $intake->application_fee,
                'programme' => $intake->programme ? [
                    'id' => $intake->programme->id,
                    'code' => $intake->programme->code,
                    'name' => $intake->programme->name,
                    'level' => $intake->programme->level,
                    'duration_semesters' => $intake->programme->duration_semesters,
                ] : null,
                'academic_session' => $intake->academicSession?->name,
            ]);

        return response()->json(['data' => $intakes]);
    }

    public function applications(Request $request): JsonResponse
    {
        /** @var AdmissionApplicant $applicant */
        $applicant = $request->user();
        $applications = $applicant->applications()
            ->with(['intake.programme:id,code,name,level', 'intake.academicSession:id,name'])
            ->where('institution_id', $applicant->institution_id)
            ->latest()
            ->get()
            ->map(fn (Application $application) => $this->applicationPayload($application));

        return response()->json(['data' => $applications]);
    }

    public function storeApplication(Request $request): JsonResponse
    {
        /** @var AdmissionApplicant $applicant */
        $applicant = $request->user();
        $data = $request->validate(['intake_id' => ['required', 'integer']]);
        $intake = $this->openIntake($applicant, (int) $data['intake_id']);

        $existing = $applicant->applications()
            ->where('institution_id', $applicant->institution_id)
            ->where('intake_id', $intake->id)
            ->first();
        if ($existing) {
            return response()->json([
                'message' => 'You already have an application for this intake.',
                'application' => $this->applicationPayload($existing->load('intake.programme', 'intake.academicSession')),
            ], 200);
        }

        $application = DB::transaction(function () use ($applicant, $intake) {
            $nameParts = preg_split('/\s+/', trim($applicant->full_name), 3) ?: [];
            $number = $this->newApplicationNumber($applicant->institution_id);

            return Application::create([
                'institution_id' => $applicant->institution_id,
                'applicant_id' => $applicant->id,
                'intake_id' => $intake->id,
                'first_name' => $nameParts[0] ?? null,
                'middle_name' => count($nameParts) === 3 ? $nameParts[1] : null,
                'last_name' => count($nameParts) > 1 ? end($nameParts) : null,
                'email' => $applicant->email,
                'phone' => $applicant->mobile,
                'application_number' => $number,
                'status' => 'draft',
                'category' => 'General',
                'nationality' => 'Indian',
            ]);
        });

        return response()->json([
            'message' => 'Draft application created successfully.',
            'application' => $this->applicationPayload($application->load('intake.programme', 'intake.academicSession')),
        ], 201);
    }

    public function showApplication(Request $request, int $id): JsonResponse
    {
        $application = $this->ownedApplication($request->user(), $id);
        return response()->json(['application' => $this->applicationPayload($application->load('intake.programme', 'intake.academicSession'))]);
    }

    public function updateApplication(Request $request, int $id): JsonResponse
    {
        /** @var AdmissionApplicant $applicant */
        $applicant = $request->user();
        $application = $this->ownedApplication($applicant, $id);
        if ($application->status !== 'draft') {
            return response()->json(['message' => 'Only draft applications can be edited.'], 409);
        }

        $data = $request->validate([
            'first_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'father_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mother_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'dob' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'category' => ['sometimes', 'nullable', 'string', 'max:20'],
            'nationality' => ['sometimes', 'nullable', 'string', 'max:40'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address_line' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'pin_code' => ['sometimes', 'nullable', 'string', 'max:12'],
            'last_exam_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'last_exam_board_or_univ' => ['sometimes', 'nullable', 'string', 'max:150'],
            'last_exam_year' => ['sometimes', 'nullable', 'integer', 'between:1900,'.(today()->year + 1)],
            'last_exam_percentage' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
        ]);

        // Email and intake are immutable here; identity and programme selection cannot be changed via draft patch.
        $application->fill($data)->save();

        return response()->json([
            'message' => 'Draft saved successfully.',
            'application' => $this->applicationPayload($application->fresh(['intake.programme', 'intake.academicSession'])),
        ]);
    }

    private function openIntake(AdmissionApplicant $applicant, int $intakeId): AdmissionIntake
    {
        return AdmissionIntake::query()
            ->where('institution_id', $applicant->institution_id)
            ->whereKey($intakeId)
            ->where('status', 'open')
            ->whereDate('open_date', '<=', today())
            ->whereDate('close_date', '>=', today())
            ->whereHas('programme', fn ($q) => $q->where('status', 'active'))
            ->first() ?? throw ValidationException::withMessages([
                'intake_id' => ['The selected admission intake is unavailable.'],
            ]);
    }

    private function ownedApplication(AdmissionApplicant $applicant, int $id): Application
    {
        return Application::query()
            ->where('institution_id', $applicant->institution_id)
            ->where('applicant_id', $applicant->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function newApplicationNumber(int $institutionId): string
    {
        do {
            $number = 'APP-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
        } while (Application::where('institution_id', $institutionId)->where('application_number', $number)->exists());
        return $number;
    }

    private function profilePayload(AdmissionApplicant $applicant): array
    {
        return [
            'id' => $applicant->id,
            'full_name' => $applicant->full_name,
            'email' => $applicant->email,
            'mobile_country_code' => $applicant->mobile_country_code,
            'mobile' => $applicant->mobile,
            'email_verified_at' => $applicant->email_verified_at?->toISOString(),
            'mobile_verified_at' => $applicant->mobile_verified_at?->toISOString(),
            'institution' => $applicant->institution ? [
                'id' => $applicant->institution->id,
                'code' => $applicant->institution->code,
                'name' => $applicant->institution->name,
                'slug' => $applicant->institution->slug,
            ] : null,
        ];
    }

    private function applicationPayload(Application $application): array
    {
        return [
            'id' => $application->id,
            'application_number' => $application->application_number,
            'status' => $application->status,
            'intake_id' => $application->intake_id,
            'programme' => $application->intake?->programme?->name,
            'programme_code' => $application->intake?->programme?->code,
            'intake' => $application->intake?->name,
            'academic_session' => $application->intake?->academicSession?->name,
            'full_name' => $application->full_name,
            'first_name' => $application->first_name,
            'middle_name' => $application->middle_name,
            'last_name' => $application->last_name,
            'father_name' => $application->father_name,
            'mother_name' => $application->mother_name,
            'dob' => $application->dob?->toDateString(),
            'gender' => $application->gender,
            'category' => $application->category,
            'nationality' => $application->nationality,
            'phone' => $application->phone,
            'address_line' => $application->address_line,
            'city' => $application->city,
            'state' => $application->state,
            'pin_code' => $application->pin_code,
            'last_exam_name' => $application->last_exam_name,
            'last_exam_board_or_univ' => $application->last_exam_board_or_univ,
            'last_exam_year' => $application->last_exam_year,
            'last_exam_percentage' => $application->last_exam_percentage,
            'updated_at' => $application->updated_at?->toISOString(),
        ];
    }
}
