<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplicant;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplicantApplicationFormController extends Controller
{
    public function show(Request $request, int $id): JsonResponse
    {
        $application = $this->owned($request->user(), $id);
        $version = $this->resolveVersion($application);
        $sections = [];
        $answers = [];
        if ($version) {
            $sections = DB::table('admission_form_sections')->where('institution_id', $application->institution_id)
                ->where('form_version_id', $version->id)->where('is_visible', true)->orderBy('sort_order')->get()
                ->map(function ($section) use ($application) {
                    $fields = DB::table('admission_form_fields')->where('institution_id', $application->institution_id)
                        ->where('section_id', $section->id)->where('is_visible', true)->orderBy('sort_order')->get()
                        ->map(function ($field) use ($application) {
                            $field->validation_rules = $field->validation_rules ? json_decode($field->validation_rules, true) : [];
                            $field->options = DB::table('admission_form_field_options')->where('institution_id', $application->institution_id)
                                ->where('field_id', $field->id)->where('is_active', true)->orderBy('sort_order')
                                ->get(['option_value as value', 'option_label as label'])->values();
                            return $field;
                        })->values();
                    return ['id' => $section->id, 'key' => $section->section_key, 'title' => $section->title,
                        'description' => $section->description, 'required' => (bool) $section->is_required, 'fields' => $fields];
                })->values();
            $answers = DB::table('admission_application_answers')->where('institution_id', $application->institution_id)
                ->where('application_id', $application->id)->where('form_version_id', $version->id)
                ->pluck('answer_value', 'field_key')->map(fn ($v) => $v === null ? null : json_decode($v, true));
        }
        $qualifications = DB::table('admission_application_qualifications')->where('institution_id', $application->institution_id)
            ->where('application_id', $application->id)->orderBy('sort_order')->orderBy('id')->get();
        $documents = DB::table('admission_application_documents')
            ->where('institution_id', $application->institution_id)
            ->where('application_id', $application->id)
            ->orderBy('document_type')
            ->orderByDesc('created_at')
            ->get(['id', 'document_type', 'original_filename', 'mime_type', 'file_size', 'verification_status', 'review_notes', 'reviewed_at', 'created_at']);
        return response()->json(['form_version' => $version ? ['id' => $version->id, 'version_number' => $version->version_number] : null,
            'sections' => $sections, 'answers' => $answers, 'qualifications' => $qualifications,
            'documents' => $documents,
            'application' => ['id' => $application->id, 'status' => $application->status, 'application_number' => $application->application_number]]);
    }

    public function saveAnswers(Request $request, int $id): JsonResponse
    {
        $application = $this->editable($request->user(), $id);
        $data = $request->validate(['answers' => ['required','array','max:200']]);
        $version = $this->resolveVersion($application);
        if (!$version) return response()->json(['message' => 'No published admission form is configured for this intake.'], 409);
        $fields = DB::table('admission_form_fields as f')->join('admission_form_sections as s','s.id','=','f.section_id')
            ->where('f.institution_id',$application->institution_id)->where('s.form_version_id',$version->id)
            ->where('f.is_visible',true)->where('f.is_applicant_editable',true)
            ->get(['f.field_key','f.field_type','f.validation_rules','f.is_required']);
        $allowed = $fields->keyBy('field_key');
        foreach ($data['answers'] as $key => $value) {
            if (!$allowed->has($key)) return response()->json(['message' => "Field {$key} is not editable in this form."], 422);
            $field = $allowed->get($key);
            if ($field->is_required && ($value === null || $value === '')) {
                return response()->json(['message' => "{$key} is required."], 422);
            }
            $rules = $field->validation_rules ? json_decode($field->validation_rules, true) : [];
            if (is_array($rules) && isset($rules['max']) && is_string($value) && mb_strlen($value) > (int)$rules['max']) {
                return response()->json(['message' => "{$key} exceeds the permitted length."], 422);
            }
            if (is_array($rules) && isset($rules['min']) && is_numeric($value) && $value < $rules['min']) {
                return response()->json(['message' => "{$key} is below the permitted minimum."], 422);
            }
            DB::table('admission_application_answers')->updateOrInsert(
                ['institution_id'=>$application->institution_id,'application_id'=>$application->id,'form_version_id'=>$version->id,'field_key'=>$key],
                ['answer_value'=>json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'updated_at'=>now(),'created_at'=>now()]
            );
        }
        return response()->json(['message'=>'Application section saved successfully.']);
    }

    public function saveQualifications(Request $request, int $id): JsonResponse
    {
        $application = $this->editable($request->user(), $id);
        $data = $request->validate(['qualifications'=>'required|array|max:20',
            'qualifications.*.id'=>'sometimes|integer',
            'qualifications.*.qualification_level'=>['required','string',Rule::in(['secondary','higher_secondary','diploma','undergraduate','postgraduate','doctorate','other'])],
            'qualifications.*.qualification_name'=>'required|string|max:180',
            'qualifications.*.board_or_university'=>'nullable|string|max:180',
            'qualifications.*.institution_name'=>'nullable|string|max:180',
            'qualifications.*.country'=>'nullable|string|max:80','qualifications.*.state'=>'nullable|string|max:100',
            'qualifications.*.roll_number'=>'nullable|string|max:100','qualifications.*.passing_year'=>'nullable|integer|between:1900,'.(today()->year+1),
            'qualifications.*.result_status'=>['nullable','string',Rule::in(['passed','appearing','failed','awaiting_result'])],
            'qualifications.*.maximum_marks'=>'nullable|numeric|min:0','qualifications.*.marks_obtained'=>'nullable|numeric|min:0',
            'qualifications.*.cgpa'=>'nullable|numeric|min:0|max:10','qualifications.*.percentage'=>'nullable|numeric|min:0|max:100',
            'qualifications.*.stream_or_subjects'=>'nullable|string|max:255']);
        DB::transaction(function () use ($data,$application) {
            $existing = DB::table('admission_application_qualifications')->where('institution_id',$application->institution_id)->where('application_id',$application->id)->pluck('id')->all();
            $kept=[];
            foreach ($data['qualifications'] as $index=>$row) {
                $id=$row['id']??null; unset($row['id']);
                $payload=['institution_id'=>$application->institution_id,'application_id'=>$application->id,
                    'qualification_level'=>$row['qualification_level'],'qualification_name'=>$row['qualification_name'],
                    'board_or_university'=>$row['board_or_university']??null,'institution_name'=>$row['institution_name']??null,
                    'country'=>$row['country']??null,'state'=>$row['state']??null,'roll_number'=>$row['roll_number']??null,
                    'passing_year'=>$row['passing_year']??null,'result_status'=>$row['result_status']??'passed',
                    'maximum_marks'=>$row['maximum_marks']??null,'marks_obtained'=>$row['marks_obtained']??null,
                    'cgpa'=>$row['cgpa']??null,'percentage'=>$row['percentage']??null,'stream_or_subjects'=>$row['stream_or_subjects']??null,
                    'sort_order'=>$index,'updated_at'=>now()];
                if ($id && in_array($id,$existing,true)) { DB::table('admission_application_qualifications')->where('id',$id)->where('application_id',$application->id)->where('institution_id',$application->institution_id)->update($payload); $kept[]=$id; }
                else { $payload['created_at']=now(); $kept[]=DB::table('admission_application_qualifications')->insertGetId($payload); }
            }
            DB::table('admission_application_qualifications')->where('institution_id',$application->institution_id)->where('application_id',$application->id)->whereNotIn('id',$kept ?: [0])->delete();
        });
        return response()->json(['message'=>'Academic qualifications saved successfully.']);
    }

    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $applicant = $request->user();
        $application = $this->owned($applicant, $id);
        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:120'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $version = $this->resolveVersion($application);
        if (!$version) {
            return response()->json(['message' => 'No published admission form is configured for this intake.'], 409);
        }

        $field = DB::table('admission_form_fields as f')
            ->join('admission_form_sections as s', 's.id', '=', 'f.section_id')
            ->where('f.institution_id', $application->institution_id)
            ->where('s.form_version_id', $version->id)
            ->where('s.is_visible', true)
            ->where('f.is_visible', true)
            ->where('f.is_applicant_editable', true)
            ->where('f.field_key', $data['document_type'])
            ->where('f.field_type', 'file')
            ->first();

        if (!$field) {
            throw ValidationException::withMessages([
                'document_type' => ['This document is not configured as an applicant-uploadable field.'],
            ]);
        }

        $file = $data['file'];
        $directory = "admissions/{$application->institution_id}/applications/{$application->id}";
        $filename = Str::uuid()->toString().'.'.$file->guessExtension();
        $storageKey = $file->storeAs($directory, $filename, 'local');

        if (!$storageKey) {
            return response()->json(['message' => 'The document could not be stored. Please try again.'], 500);
        }

        try {
            $document = DB::transaction(function () use ($application, $data, $file, $storageKey) {
                $locked = Application::query()
                    ->where('institution_id', $application->institution_id)
                    ->whereKey($application->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldDocuments = DB::table('admission_application_documents')
                    ->where('institution_id', $locked->institution_id)
                    ->where('application_id', $locked->id)
                    ->where('document_type', $data['document_type'])
                    ->orderByDesc('id')
                    ->get(['id', 'storage_disk', 'storage_key', 'verification_status']);

                if ($locked->status !== 'draft') {
                    abort_unless(
                        $oldDocuments->contains(fn ($document) => $document->verification_status === 'returned'),
                        409,
                        'After submission, only a document returned by the institution can be replaced.'
                    );
                }

                $documentId = DB::table('admission_application_documents')->insertGetId([
                    'institution_id' => $locked->institution_id,
                    'application_id' => $locked->id,
                    'document_type' => $data['document_type'],
                    'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'storage_disk' => 'local',
                    'storage_key' => $storageKey,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                    'verification_status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($locked->status === 'draft') {
                    DB::table('admission_application_documents')
                        ->whereIn('id', $oldDocuments->pluck('id')->all())
                        ->delete();
                } else {
                    DB::table('admission_application_documents')
                        ->where('institution_id', $locked->institution_id)
                        ->where('application_id', $locked->id)
                        ->where('document_type', $data['document_type'])
                        ->where('verification_status', 'returned')
                        ->update(['verification_status' => 'superseded', 'updated_at' => now()]);

                    DB::table('admission_application_events')->insert([
                        'institution_id' => $locked->institution_id,
                        'application_id' => $locked->id,
                        'event_type' => 'document_reuploaded',
                        'from_status' => 'returned',
                        'to_status' => 'pending',
                        'remarks' => 'Applicant uploaded a replacement document.',
                        'actor_type' => 'applicant',
                        'actor_id' => $locked->applicant_id,
                        'metadata' => json_encode(['document_id' => $documentId, 'document_type' => $data['document_type']], JSON_THROW_ON_ERROR),
                        'occurred_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return [
                    'document' => DB::table('admission_application_documents')->where('id', $documentId)
                        ->first(['id', 'document_type', 'original_filename', 'mime_type', 'file_size', 'verification_status', 'created_at']),
                    'old_documents' => $locked->status === 'draft' ? $oldDocuments : collect(),
                ];
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storageKey);
            throw $exception;
        }

        foreach ($document['old_documents'] as $oldDocument) {
            Storage::disk($oldDocument->storage_disk)->delete($oldDocument->storage_key);
        }

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'document' => $document['document'],
        ], 201);
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'declaration_accepted' => ['required', 'accepted'],
        ]);

        $applicant = $request->user();

        $result = DB::transaction(function () use ($applicant, $id, $data) {
            $application = Application::query()
                ->where('institution_id', $applicant->institution_id)
                ->where('applicant_id', $applicant->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status !== 'draft') {
                throw ValidationException::withMessages([
                    'application' => ['This application has already been submitted or is no longer editable.'],
                ]);
            }

            $version = $this->resolveVersion($application);
            if (!$version) {
                return ['conflict' => 'No published admission form is configured for this intake.'];
            }

            $requiredErrors = [];
            $requiredBaseFields = [
                'first_name' => 'First name',
                'last_name' => 'Last name',
                'dob' => 'Date of birth',
                'phone' => 'Mobile number',
                'address_line' => 'Correspondence address',
                'city' => 'City',
                'state' => 'State',
                'pin_code' => 'PIN / postal code',
            ];

            foreach ($requiredBaseFields as $column => $label) {
                if (blank($application->{$column})) {
                    $requiredErrors["application.{$column}"] = ["{$label} is required before submission."];
                }
            }

            $sections = DB::table('admission_form_sections')
                ->where('institution_id', $application->institution_id)
                ->where('form_version_id', $version->id)
                ->where('is_visible', true)
                ->get();

            $fields = DB::table('admission_form_fields as f')
                ->join('admission_form_sections as s', 's.id', '=', 'f.section_id')
                ->where('f.institution_id', $application->institution_id)
                ->where('s.form_version_id', $version->id)
                ->where('s.is_visible', true)
                ->where('f.is_visible', true)
                ->where('f.is_required', true)
                ->get(['f.id as field_id', 'f.field_key', 'f.label', 'f.field_type', 'f.is_applicant_editable']);

            $answers = DB::table('admission_application_answers')
                ->where('institution_id', $application->institution_id)
                ->where('application_id', $application->id)
                ->where('form_version_id', $version->id)
                ->pluck('answer_value', 'field_key')
                ->map(fn ($value) => $value === null ? null : json_decode($value, true));

            foreach ($fields as $field) {
                // These fields are derived from the selected intake or applicant account
                // and are intentionally not editable through the dynamic answer API.
                if (!$field->is_applicant_editable) {
                    continue;
                }

                if ($field->field_type === 'file') {
                    $exists = DB::table('admission_application_documents')
                        ->where('institution_id', $application->institution_id)
                        ->where('application_id', $application->id)
                        ->where('document_type', $field->field_key)
                        ->exists();
                    if (!$exists) {
                        $requiredErrors["documents.{$field->field_key}"] = ["{$field->label} must be uploaded."];
                    }
                    continue;
                }

                $value = $answers->get($field->field_key);
                if ($field->field_type === 'boolean') {
                    if (!in_array($value, [true, 1, '1', 'true'], true)) {
                        $requiredErrors["answers.{$field->field_key}"] = ["{$field->label} must be accepted."];
                    }
                    continue;
                }

                if (blank($value)) {
                    $requiredErrors["answers.{$field->field_key}"] = ["{$field->label} is required."];
                    continue;
                }

                if (in_array($field->field_type, ['select', 'dropdown', 'radio'], true)) {
                    $optionExists = DB::table('admission_form_field_options')
                        ->where('institution_id', $application->institution_id)
                        ->where('field_id', $field->field_id)
                        ->where('option_value', (string) $value)
                        ->where('is_active', true)
                        ->exists();
                    if (!$optionExists) {
                        $requiredErrors["answers.{$field->field_key}"] = ["Select a valid {$field->label} option."];
                    }
                }
            }

            $academicSectionRequired = $sections->contains(
                fn ($section) => $section->section_key === 'academic' && (bool) $section->is_required
            );

            if ($academicSectionRequired) {
                $qualificationCount = DB::table('admission_application_qualifications')
                    ->where('institution_id', $application->institution_id)
                    ->where('application_id', $application->id)
                    ->count();

                if ($qualificationCount === 0) {
                    $requiredErrors['qualifications'] = ['Add at least one academic qualification before submission.'];
                }
            }

            if ($requiredErrors) {
                throw ValidationException::withMessages($requiredErrors);
            }

            $submittedAt = now();

            $application->status = 'under_review';
            $application->save();

            DB::table('admission_application_events')->insert([
                'institution_id' => $application->institution_id,
                'application_id' => $application->id,
                'event_type' => 'applicant_submitted',
                'from_status' => 'draft',
                'to_status' => 'under_review',
                'remarks' => 'Application submitted by applicant.',
                'actor_type' => 'applicant',
                'actor_id' => $applicant->id,
                'metadata' => json_encode([
                    'declaration_accepted' => (bool) $data['declaration_accepted'],
                    'declaration_version' => 'admission-application-v1',
                    'declaration_accepted_at' => $submittedAt->toISOString(),
                ], JSON_UNESCAPED_SLASHES),
                'occurred_at' => $submittedAt,
                'created_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ]);

            return [
                'application' => $application->fresh(['intake.programme', 'intake.academicSession']),
                'submitted_at' => $submittedAt->toISOString(),
            ];
        });

        if (isset($result['conflict'])) {
            return response()->json(['message' => $result['conflict']], 409);
        }

        return response()->json([
            'message' => 'Your application has been submitted successfully and is now under review.',
            'application' => [
                'id' => $result['application']->id,
                'application_number' => $result['application']->application_number,
                'status' => $result['application']->status,
                'submitted_at' => $result['submitted_at'],
            ],
        ]);
    }

    private function resolveVersion(Application $application): ?object
    {
        if ($application->form_version_id) return DB::table('admission_form_versions')->where('institution_id',$application->institution_id)->where('id',$application->form_version_id)->where('status','published')->first();
        $intake=DB::table('admission_intakes')->where('institution_id',$application->institution_id)->where('id',$application->intake_id)->first();
        if (!$intake) return null;
        $version=DB::table('admission_form_versions as v')->join('admission_form_templates as t','t.id','=','v.template_id')
            ->where('v.institution_id',$application->institution_id)->where('v.status','published')
            ->where(function($q) use ($intake) { $q->where('t.intake_id',$intake->id)->orWhere(function($q) use ($intake){$q->whereNull('t.intake_id')->where(function($q) use ($intake){$q->where('t.programme_id',$intake->programme_id)->orWhereNull('t.programme_id');});}); })
            ->orderByRaw('CASE WHEN t.intake_id IS NOT NULL THEN 0 WHEN t.programme_id IS NOT NULL THEN 1 ELSE 2 END')
            ->orderByDesc('v.version_number')->select('v.*')->first();
        if ($version) $application->forceFill(['form_version_id'=>$version->id])->save();
        return $version;
    }

    private function owned(AdmissionApplicant $applicant, int $id): Application
    { return Application::query()->where('institution_id',$applicant->institution_id)->where('applicant_id',$applicant->id)->whereKey($id)->firstOrFail(); }
    private function editable(AdmissionApplicant $applicant, int $id): Application
    { $application=$this->owned($applicant,$id); abort_unless($application->status==='draft',409,'Only draft applications can be edited.'); return $application; }
}
