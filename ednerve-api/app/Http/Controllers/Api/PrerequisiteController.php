<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectPrerequisite;
use App\Services\AuditService;
use Illuminate\Http\Request;

class PrerequisiteController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * List prerequisites for a subject (same institution).
     */
    public function index(Request $request, int $subjectId)
    {
        $institutionId = $request->user()->institution_id;

        $subject = Subject::where('institution_id', $institutionId)->findOrFail($subjectId);

        $prerequisites = SubjectPrerequisite::where('institution_id', $institutionId)
            ->where('subject_id', $subject->id)
            ->with('prerequisite:id,code,name,credits')
            ->get()
            ->map(fn (SubjectPrerequisite $row) => [
                'id' => $row->id,
                'prerequisite' => $row->prerequisite,
            ]);

        return response()->json(['prerequisites' => $prerequisites]);
    }

    /**
     * Add a prerequisite to a subject.
     *
     * Guards: same-institution subjects only, no self-prerequisite,
     * and no dependency cycles (transitive closure check).
     */
    public function store(Request $request, int $subjectId)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'prerequisite_subject_id' => ['required', 'integer', 'min:1'],
        ]);

        $subject = Subject::where('institution_id', $institutionId)->findOrFail($subjectId);

        $prerequisite = Subject::where('institution_id', $institutionId)
            ->findOrFail((int) $validated['prerequisite_subject_id']);

        abort_if(
            $prerequisite->id === $subject->id,
            422,
            'A subject cannot be its own prerequisite.'
        );

        $already = SubjectPrerequisite::where('institution_id', $institutionId)
            ->where('subject_id', $subject->id)
            ->where('prerequisite_subject_id', $prerequisite->id)
            ->exists();

        abort_if($already, 422, 'This prerequisite is already attached to the subject.');

        abort_if(
            $this->wouldCreateCycle($institutionId, $subject->id, $prerequisite->id),
            422,
            'This prerequisite would create a circular dependency.'
        );

        $row = SubjectPrerequisite::create([
            'institution_id' => $institutionId,
            'subject_id' => $subject->id,
            'prerequisite_subject_id' => $prerequisite->id,
        ]);

        $this->audit->record(
            $request,
            event: 'subject.prerequisite_added',
            module: 'institution',
            subject: $subject,
            newValues: ['prerequisite_subject_id' => $prerequisite->id, 'prerequisite_code' => $prerequisite->code],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Prerequisite added.',
            'prerequisite' => [
                'id' => $row->id,
                'prerequisite' => [
                    'id' => $prerequisite->id,
                    'code' => $prerequisite->code,
                    'name' => $prerequisite->name,
                    'credits' => $prerequisite->credits,
                ],
            ],
        ], 201);
    }

    public function destroy(Request $request, int $subjectId, int $prerequisiteId)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $row = SubjectPrerequisite::where('institution_id', $institutionId)
            ->where('subject_id', $subjectId)
            ->where('id', $prerequisiteId)
            ->firstOrFail();

        $subject = Subject::where('institution_id', $institutionId)->findOrFail($subjectId);

        $row->delete();

        $this->audit->record(
            $request,
            event: 'subject.prerequisite_removed',
            module: 'institution',
            subject: $subject,
            oldValues: ['prerequisite_subject_id' => $row->prerequisite_subject_id],
            institutionId: $institutionId,
        );

        return response()->json(['message' => 'Prerequisite removed.']);
    }

    /**
     * Would attaching $prerequisiteId as a prerequisite of $subjectId
     * create a cycle? True if $prerequisiteId transitively depends on
     * $subjectId through existing prerequisite edges.
     */
    private function wouldCreateCycle(int $institutionId, int $subjectId, int $prerequisiteId): bool
    {
        $visited = [];
        $stack = [$prerequisiteId];

        while ($stack) {
            $current = array_pop($stack);

            if ($current === $subjectId) {
                return true;
            }

            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            $edges = SubjectPrerequisite::where('institution_id', $institutionId)
                ->where('subject_id', $current)
                ->pluck('prerequisite_subject_id')
                ->all();

            foreach ($edges as $edge) {
                if (!isset($visited[$edge])) {
                    $stack[] = $edge;
                }
            }
        }

        return false;
    }
}
