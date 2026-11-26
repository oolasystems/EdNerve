<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplicant;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        return response()->json(['form_version' => $version ? ['id' => $version->id, 'version_number' => $version->version_number] : null,
            'sections' => $sections, 'answers' => $answers, 'qualifications' => $qualifications,
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
