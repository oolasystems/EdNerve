<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    use RequiresAccountType;

    /**
     * Paginated audit feed with filters for the platform console.
     */
    public function index(Request $request)
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'module' => ['nullable', 'string', 'max:80'],
            'event' => ['nullable', 'string', 'max:80'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'actor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = AuditLog::query()
            ->with([
                'actor:id,name,email',
                'institution:id,code,name',
            ]);

        if (!empty($validated['q'])) {
            $q = $validated['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('description', 'like', "%{$q}%")
                    ->orWhere('ip_address', 'like', "%{$q}%")
                    ->orWhere('event', 'like', "%{$q}%")
                    ->orWhere('module', 'like', "%{$q}%");
            });
        }

        if (!empty($validated['module'])) {
            $query->where('module', $validated['module']);
        }

        if (!empty($validated['event'])) {
            $query->where('event', $validated['event']);
        }

        if (!empty($validated['institution_id'])) {
            $query->where('institution_id', $validated['institution_id']);
        }

        if (!empty($validated['actor_user_id'])) {
            $query->where('actor_user_id', $validated['actor_user_id']);
        }

        if (!empty($validated['date_from'])) {
            $query->where('created_at', '>=', $validated['date_from'] . ' 00:00:00');
        }

        if (!empty($validated['date_to'])) {
            $query->where('created_at', '<=', $validated['date_to'] . ' 23:59:59');
        }

        $perPage = $validated['per_page'] ?? 20;

        $entries = $query->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($entries);
    }
}
