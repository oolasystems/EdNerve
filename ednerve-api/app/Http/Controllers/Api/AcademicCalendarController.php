<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\AcademicCalendarEvent;
use App\Models\AcademicSession;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicCalendarController extends Controller
{
    use RequiresAccountType;

    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;
        $query = AcademicCalendarEvent::where('institution_id', $institutionId)
            ->with(['academicSession:id,code,name']);

        if ($request->filled('academic_session_id')) {
            $query->where('academic_session_id', (int) $request->query('academic_session_id'));
        }
        if ($request->filled('event_type')) {
            $query->where('event_type', (string) $request->query('event_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }
        if ($request->filled('from')) {
            $query->whereDate('starts_on', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('starts_on', '<=', (string) $request->query('to'));
        }

        $events = $query->orderBy('starts_on')->orderBy('id')->get();

        return response()->json(['events' => $events]);
    }

    public function store(Request $request)
    {
        $this->ensureInstitutionAdmin($request);
        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'academic_session_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'event_type' => ['required', Rule::in(['holiday', 'exam', 'registration', 'fee_deadline', 'class', 'other'])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_all_day' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if (!empty($validated['academic_session_id'])) {
            $exists = AcademicSession::where('institution_id', $institutionId)
                ->where('id', $validated['academic_session_id'])->exists();
            abort_unless($exists, 422, 'Invalid academic session.');
        }

        $event = AcademicCalendarEvent::create([
            'institution_id' => $institutionId,
            'academic_session_id' => $validated['academic_session_id'] ?? null,
            'title' => $validated['title'],
            'event_type' => $validated['event_type'],
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'] ?? null,
            'is_all_day' => $validated['is_all_day'] ?? true,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);
        $event->load('academicSession:id,code,name');

        $this->audit->record($request, 'academic_calendar.created', 'academic', $event, [], $event->toArray(), null, $institutionId);

        return response()->json(['message' => 'Calendar event created.', 'event' => $event], 201);
    }

    public function show(Request $request, int $id)
    {
        $event = AcademicCalendarEvent::where('institution_id', $request->user()->institution_id)
            ->with(['academicSession:id,code,name'])
            ->findOrFail($id);
        return response()->json(['event' => $event]);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);
        $institutionId = $request->user()->institution_id;
        $event = AcademicCalendarEvent::where('institution_id', $institutionId)->findOrFail($id);
        $validated = $request->validate([
            'academic_session_id' => ['nullable', 'integer'],
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'event_type' => ['sometimes', 'required', Rule::in(['holiday', 'exam', 'registration', 'fee_deadline', 'class', 'other'])],
            'starts_on' => ['sometimes', 'required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_all_day' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if (array_key_exists('academic_session_id', $validated) && !empty($validated['academic_session_id'])) {
            $exists = AcademicSession::where('institution_id', $institutionId)
                ->where('id', $validated['academic_session_id'])->exists();
            abort_unless($exists, 422, 'Invalid academic session.');
        }

        $before = $event->toArray();
        $event->fill($validated);
        $event->save();
        $event->load('academicSession:id,code,name');

        $this->audit->record($request, 'academic_calendar.updated', 'academic', $event, $before, $event->toArray(), null, $institutionId);

        return response()->json(['message' => 'Calendar event updated.', 'event' => $event]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);
        $institutionId = $request->user()->institution_id;
        $event = AcademicCalendarEvent::where('institution_id', $institutionId)->findOrFail($id);
        $before = $event->toArray();
        $event->delete();

        $this->audit->record($request, 'academic_calendar.deleted', 'academic', null, $before, ['id' => $id], null, $institutionId);

        return response()->json(['message' => 'Calendar event deleted.']);
    }
}
