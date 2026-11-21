<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\Syllabus;
use App\Models\SyllabusUnit;
use App\Models\Subject;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Version-controlled syllabus lifecycle (Phase A).
 *
 * States: draft -> in_review -> approved -> published -> archived
 * Rules:
 *  - Only draft syllabi are editable.
 *  - Author != approver (separation of duties).
 *  - Published syllabi are immutable; a new version is created via clone().
 *  - Publishing supersedes the previous published version for the same
 *    subject (same session, or a session-less syllabus) and archives it.
 */
class SyllabusController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $query = Syllabus::where('institution_id', $institutionId)
            ->with(['subject:id,code,name,credits', 'academicSession:id,code,name', 'author:id,name'])
            ->orderByDesc('id');

        if ($subjectId = $request->query('subject_id')) {
            $query->where('subject_id', (int) $subjectId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(['syllabi' => $query->get()]);
    }

    public function show(Request $request, int $id)
    {
        $institutionId = $request->user()->institution_id;

        $syllabus = $this->findSyllabus($institutionId, $id);

        $syllabus->load([
            'subject:id,code,name,credits',
            'academicSession:id,code,name',
            'author:id,name',
            'approver:id,name',
            'units',
        ]);

        return response()->json(['syllabus' => $syllabus]);
    }

    public function store(Request $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $this->validatePayload($request, forCreate: true);

        $subject = Subject::where('institution_id', $institutionId)->findOrFail((int) $validated['subject_id']);

        abort_if(
            $this->versionExists($institutionId, $subject->id, $validated['version']),
            422,
            'A syllabus version with this code already exists for the subject.'
        );

        $syllabus = DB::transaction(function () use ($institutionId, $subject, $validated, $request) {
            $syllabus = Syllabus::create([
                'institution_id' => $institutionId,
                'subject_id' => $subject->id,
                'academic_session_id' => $validated['academic_session_id'] ?? null,
                'version' => $validated['version'],
                'title' => $validated['title'],
                'status' => 'draft',
                'author_id' => $request->user()->id,
            ]);

            $this->syncUnits($institutionId, $syllabus, $validated['units'] ?? []);

            return $syllabus;
        });

        $this->audit->record(
            $request,
            event: 'syllabus.created',
            module: 'institution',
            subject: $syllabus,
            newValues: ['subject_id' => $subject->id, 'version' => $syllabus->version],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Syllabus draft created.',
            'syllabus' => $this->freshWithUnits($syllabus),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $syllabus = $this->findSyllabus($institutionId, $id);

        abort_unless(
            in_array($syllabus->status, Syllabus::EDITABLE_STATUSES, true),
            422,
            'Only draft syllabi can be edited. Clone the published version to create a new draft.'
        );

        $validated = $this->validatePayload($request, forCreate: false);

        if (isset($validated['academic_session_id'])) {
            // Session must belong to the same institution
            $sessionExists = DB::table('academic_sessions')
                ->where('institution_id', $institutionId)
                ->where('id', $validated['academic_session_id'])
                ->exists();
            abort_unless($sessionExists, 422, 'Academic session not found.');
        }

        if (isset($validated['version']) && $validated['version'] !== $syllabus->version) {
            abort_if(
                $this->versionExists($institutionId, $syllabus->subject_id, $validated['version']),
                422,
                'A syllabus version with this code already exists for the subject.'
            );
        }

        $before = $syllabus->toArray();

        DB::transaction(function () use ($institutionId, $syllabus, $validated) {
            $syllabus->fill([
                'academic_session_id' => $validated['academic_session_id'] ?? $syllabus->academic_session_id,
                'version' => $validated['version'] ?? $syllabus->version,
                'title' => $validated['title'] ?? $syllabus->title,
            ]);
            $syllabus->save();

            if (array_key_exists('units', $validated)) {
                $this->syncUnits($institutionId, $syllabus, $validated['units']);
            }
        });

        $this->audit->record(
            $request,
            event: 'syllabus.updated',
            module: 'institution',
            subject: $syllabus,
            oldValues: $before,
            newValues: $syllabus->fresh()->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Syllabus draft updated.',
            'syllabus' => $this->freshWithUnits($syllabus),
        ]);
    }

    /**
     * draft -> in_review
     */
    public function submit(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $syllabus = $this->findSyllabus($institutionId, $id);

        abort_unless($syllabus->status === 'draft', 422, 'Only drafts can be submitted for review.');

        $syllabus->update(['status' => 'in_review']);

        $this->audit->record(
            $request,
            event: 'syllabus.submitted_for_review',
            module: 'institution',
            subject: $syllabus,
            institutionId: $institutionId,
        );

        return response()->json(['message' => 'Syllabus submitted for review.', 'syllabus' => $this->freshWithUnits($syllabus)]);
    }

    /**
     * in_review -> approved (SoD: approver != author)
     */
    public function approve(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $syllabus = $this->findSyllabus($institutionId, $id);

        abort_unless($syllabus->status === 'in_review', 422, 'Only syllabi under review can be approved.');
        abort_if(
            $syllabus->author_id === $request->user()->id,
            403,
            'Separation of duties: the author cannot approve their own syllabus.'
        );

        $syllabus->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $this->audit->record(
            $request,
            event: 'syllabus.approved',
            module: 'institution',
            subject: $syllabus,
            institutionId: $institutionId,
        );

        return response()->json(['message' => 'Syllabus approved.', 'syllabus' => $this->freshWithUnits($syllabus)]);
    }

    /**
     * approved -> published; supersedes the prior published version.
     */
    public function publish(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $syllabus = $this->findSyllabus($institutionId, $id);

        abort_unless($syllabus->status === 'approved', 422, 'Only approved syllabi can be published.');

        DB::transaction(function () use ($institutionId, $syllabus, $request) {
            $supersededIds = Syllabus::where('institution_id', $institutionId)
                ->where('subject_id', $syllabus->subject_id)
                ->where('status', 'published')
                ->where('id', '!=', $syllabus->id)
                ->when(
                    $syllabus->academic_session_id,
                    fn ($q) => $q->where('academic_session_id', $syllabus->academic_session_id),
                    fn ($q) => $q->whereNull('academic_session_id'),
                )
                ->pluck('id');

            if ($supersededIds->isNotEmpty()) {
                Syllabus::whereIn('id', $supersededIds)->update([
                    'status' => 'archived',
                    'superseded_by' => $syllabus->id,
                ]);
            }

            $syllabus->update([
                'status' => 'published',
                'published_by' => $request->user()->id,
                'published_at' => now(),
            ]);
        });

        $this->audit->record(
            $request,
            event: 'syllabus.published',
            module: 'institution',
            subject: $syllabus,
            newValues: ['status' => 'published'],
            institutionId: $institutionId,
        );

        return response()->json(['message' => 'Syllabus published.', 'syllabus' => $this->freshWithUnits($syllabus)]);
    }

    /**
     * Return to draft (only one step backwards; from in_review).
     */
    public function requestChanges(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $syllabus = $this->findSyllabus($institutionId, $id);

        abort_unless($syllabus->status === 'in_review', 422, 'Only syllabi under review can be sent back for changes.');

        $syllabus->update(['status' => 'draft']);

        $this->audit->record(
            $request,
            event: 'syllabus.changes_requested',
            module: 'institution',
            subject: $syllabus,
            institutionId: $institutionId,
        );

        return response()->json(['message' => 'Syllabus returned to draft.', 'syllabus' => $this->freshWithUnits($syllabus)]);
    }

    /**
     * Clone any syllabus into a new draft (same units), next free version
     * suffix when the caller doesn't supply one.
     */
    public function clone(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $source = $this->findSyllabus($institutionId, $id);

        $validated = $request->validate([
            'version' => ['sometimes', 'string', 'max:30'],
        ]);

        $version = $validated['version'] ?? $this->nextVersion($institutionId, $source->subject_id, $source->version);

        abort_if(
            $this->versionExists($institutionId, $source->subject_id, $version),
            422,
            'A syllabus version with this code already exists for the subject.'
        );

        $clone = DB::transaction(function () use ($institutionId, $source, $version, $request) {
            $clone = Syllabus::create([
                'institution_id' => $institutionId,
                'subject_id' => $source->subject_id,
                'academic_session_id' => $source->academic_session_id,
                'version' => $version,
                'title' => $source->title,
                'status' => 'draft',
                'author_id' => $request->user()->id,
            ]);

            foreach ($source->units as $unit) {
                $clone->units()->create([
                    'institution_id' => $institutionId,
                    'position' => $unit->position,
                    'unit_number' => $unit->unit_number,
                    'title' => $unit->title,
                    'topics' => $unit->topics,
                    'learning_outcomes' => $unit->learning_outcomes,
                    'prescribed_hours' => $unit->prescribed_hours,
                ]);
            }

            return $clone;
        });

        $this->audit->record(
            $request,
            event: 'syllabus.cloned',
            module: 'institution',
            subject: $clone,
            newValues: ['version' => $version, 'cloned_from' => $source->id],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Syllabus cloned as draft.',
            'syllabus' => $this->freshWithUnits($clone),
        ], 201);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $syllabus = $this->findSyllabus($institutionId, $id);

        abort_unless(
            in_array($syllabus->status, ['draft', 'in_review'], true),
            422,
            'Only draft or in-review syllabi can be archived.'
        );

        $before = $syllabus->toArray();
        $syllabus->update(['status' => 'archived']);

        $this->audit->record(
            $request,
            event: 'syllabus.archived',
            module: 'institution',
            subject: $syllabus,
            oldValues: $before,
            newValues: ['status' => 'archived'],
            institutionId: $institutionId,
        );

        return response()->json(['message' => 'Syllabus archived.']);
    }

    // ----------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------

    private function findSyllabus(int $institutionId, int $id): Syllabus
    {
        return Syllabus::where('institution_id', $institutionId)->findOrFail($id);
    }

    private function freshWithUnits(Syllabus $syllabus): Syllabus
    {
        return $syllabus->fresh()->load([
            'subject:id,code,name,credits',
            'academicSession:id,code,name',
            'author:id,name',
            'approver:id,name',
            'units',
        ]);
    }

    private function versionExists(int $institutionId, int $subjectId, string $version): bool
    {
        return Syllabus::where('institution_id', $institutionId)
            ->where('subject_id', $subjectId)
            ->where('version', $version)
            ->exists();
    }

    private function nextVersion(int $institutionId, int $subjectId, string $sourceVersion): string
    {
        // Turn '2026.1' into '2026.2' by incrementing the last numeric segment;
        // skip versions that already exist.
        if (!preg_match('/^(.*?)(\d+)$/', $sourceVersion, $m)) {
            $candidate = $sourceVersion . '-2';
            $suffix = 3;
            while ($this->versionExists($institutionId, $subjectId, $candidate)) {
                $candidate = $sourceVersion . '-' . $suffix++;
            }
            return $candidate;
        }

        $prefix = $m[1];
        $next = (int) $m[2] + 1;
        $candidate = $prefix . $next;

        while ($this->versionExists($institutionId, $subjectId, $candidate)) {
            $next++;
            $candidate = $prefix . $next;
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $forCreate): array
    {
        $institutionId = $request->user()->institution_id;

        $rules = [
            'academic_session_id' => ['nullable', 'integer', 'min:1'],
            'version' => [$forCreate ? 'required' : 'sometimes', 'string', 'max:30'],
            'title' => [$forCreate ? 'required' : 'sometimes', 'string', 'max:250'],
            'units' => ['sometimes', 'array', 'max:50'],
            'units.*.position' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'units.*.unit_number' => ['required_with:units', 'string', 'max:20'],
            'units.*.title' => ['required_with:units', 'string', 'max:250'],
            'units.*.topics' => ['nullable', 'string', 'max:20000'],
            'units.*.learning_outcomes' => ['nullable', 'string', 'max:20000'],
            'units.*.prescribed_hours' => ['sometimes', 'integer', 'min:0', 'max:500'],
        ];

        if ($forCreate) {
            $rules['subject_id'] = ['required', 'integer', 'min:1'];
        }

        $validated = $request->validate($rules);

        return $validated;
    }

    /**
     * Replace the unit set of a syllabus (position order honoured).
     */
    private function syncUnits(int $institutionId, Syllabus $syllabus, array $units): void
    {
        SyllabusUnit::where('institution_id', $institutionId)
            ->where('syllabus_id', $syllabus->id)
            ->delete();

        $position = 1;
        foreach ($units as $unit) {
            $syllabus->units()->create([
                'institution_id' => $institutionId,
                'position' => $unit['position'] ?? $position,
                'unit_number' => $unit['unit_number'],
                'title' => $unit['title'],
                'topics' => $unit['topics'] ?? null,
                'learning_outcomes' => $unit['learning_outcomes'] ?? null,
                'prescribed_hours' => $unit['prescribed_hours'] ?? 0,
            ]);
            $position++;
        }
    }
}
