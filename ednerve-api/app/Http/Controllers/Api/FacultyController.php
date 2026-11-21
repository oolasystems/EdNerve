<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFacultyRequest;
use App\Http\Requests\UpdateFacultyRequest;
use App\Models\Faculty;
use App\Services\AuditService;
use Illuminate\Http\Request;

class FacultyController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    public function index(Request $request)
    {
        $faculties = Faculty::where(
            'institution_id',
            $request->user()->institution_id
        )
            ->orderBy('name')
            ->get();

        return response()->json([
            'faculties' => $faculties,
        ]);
    }

    public function store(StoreFacultyRequest $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validated();

        $faculty = Faculty::create([
            ...$validated,
            'institution_id' => $institutionId,
            'status' => $validated['status'] ?? 'active',
        ]);

        $this->audit->record(
            $request,
            event: 'faculty.created',
            module: 'institution',
            subject: $faculty,
            newValues: $faculty->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Faculty created successfully.',
            'faculty' => $faculty,
        ], 201);
    }

    public function update(UpdateFacultyRequest $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $faculty = Faculty::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $faculty->toArray();
        $faculty->update($request->validated());

        $this->audit->record(
            $request,
            event: 'faculty.updated',
            module: 'institution',
            subject: $faculty,
            oldValues: $before,
            newValues: $faculty->fresh()?->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Faculty updated successfully.',
            'faculty' => $faculty->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $faculty = Faculty::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $faculty->toArray();
        $faculty->update(['status' => 'inactive']);

        $this->audit->record(
            $request,
            event: 'faculty.deactivated',
            module: 'institution',
            subject: $faculty,
            oldValues: $before,
            newValues: ['status' => 'inactive'],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Faculty deactivated successfully.',
        ]);
    }
}
