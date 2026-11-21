<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgrammeRequest;
use App\Http\Requests\UpdateProgrammeRequest;
use App\Models\Programme;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ProgrammeController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * List programmes for the caller's institution.
     * Optional filters: ?search=, ?level=UG|PG, ?status=
     */
    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $query = Programme::where('institution_id', $institutionId)
            ->orderBy('level')
            ->orderBy('code');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        if ($level = $request->query('level')) {
            $query->where('level', $level);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'programmes' => $query->get(),
        ]);
    }

    public function store(StoreProgrammeRequest $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $programme = Programme::create([
            ...$request->validated(),
            'institution_id' => $institutionId,
            'status' => $request->validated()['status'] ?? 'active',
        ]);

        $this->audit->record(
            $request,
            event: 'programme.created',
            module: 'institution',
            subject: $programme,
            newValues: $programme->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Programme created successfully.',
            'programme' => $programme,
        ], 201);
    }

    public function update(UpdateProgrammeRequest $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $programme = Programme::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $programme->toArray();
        $programme->update($request->validated());

        $this->audit->record(
            $request,
            event: 'programme.updated',
            module: 'institution',
            subject: $programme,
            oldValues: $before,
            newValues: $programme->fresh()?->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Programme updated successfully.',
            'programme' => $programme->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $programme = Programme::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $programme->toArray();
        $programme->update(['status' => 'inactive']);

        $this->audit->record(
            $request,
            event: 'programme.deactivated',
            module: 'institution',
            subject: $programme,
            oldValues: $before,
            newValues: ['status' => 'inactive'],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Programme deactivated successfully.',
        ]);
    }

    /**
     * List programme courses ordered by semester and configured position.
     */
    public function courses(Request $request, int $id)
    {
        $institutionId = $request->user()->institution_id;

        $programme = Programme::where('institution_id', $institutionId)
            ->findOrFail($id);

        $courses = $programme->programmeCourses()
            ->with('subject:id,code,name,credits,subject_type,status')
            ->get()
            ->map(fn ($pc) => [
                'id' => $pc->id,
                'semester' => $pc->semester,
                'course_type' => $pc->course_type,
                'position' => $pc->position,
                'status' => $pc->status,
                'subject' => $pc->subject ? [
                    'id' => $pc->subject->id,
                    'code' => $pc->subject->code,
                    'name' => $pc->subject->name,
                    'credits' => $pc->subject->credits,
                    'subject_type' => $pc->subject->subject_type,
                    'status' => $pc->subject->status,
                ] : null,
            ])
            ->values();

        return response()->json([
            'programme' => $programme,
            'courses' => $courses,
        ]);
    }
}
