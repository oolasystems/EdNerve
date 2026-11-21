<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Models\Subject;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * List subjects for the caller's institution.
     * Optional filters: ?search=, ?type=theory|practical, ?status=
     */
    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $query = Subject::where('institution_id', $institutionId)
            ->orderBy('code');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        if ($type = $request->query('type')) {
            $query->where('subject_type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'subjects' => $query->get(),
        ]);
    }

    public function store(StoreSubjectRequest $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validated();

        $subject = Subject::create([
            ...$validated,
            'institution_id' => $institutionId,
            'credits' => $validated['credits'] ?? 3,
            'subject_type' => $validated['subject_type'] ?? 'theory',
            'status' => $validated['status'] ?? 'active',
        ]);

        $this->audit->record(
            $request,
            event: 'subject.created',
            module: 'institution',
            subject: $subject,
            newValues: $subject->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Subject created successfully.',
            'subject' => $subject,
        ], 201);
    }

    public function update(UpdateSubjectRequest $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $subject = Subject::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $subject->toArray();
        $subject->update($request->validated());

        $this->audit->record(
            $request,
            event: 'subject.updated',
            module: 'institution',
            subject: $subject,
            oldValues: $before,
            newValues: $subject->fresh()?->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Subject updated successfully.',
            'subject' => $subject->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $subject = Subject::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $subject->toArray();
        $subject->update(['status' => 'inactive']);

        $this->audit->record(
            $request,
            event: 'subject.deactivated',
            module: 'institution',
            subject: $subject,
            oldValues: $before,
            newValues: ['status' => 'inactive'],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Subject deactivated successfully.',
        ]);
    }
}
