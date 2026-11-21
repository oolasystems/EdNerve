<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\SecurityAlert;
use Illuminate\Http\Request;

class SecurityAlertController extends Controller
{
    use RequiresAccountType;

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user?->isInstitutionAdmin() || $user?->isPlatformAdmin(),
            403,
            'Administrator access required.'
        );

        $query = SecurityAlert::query()
            ->orderByDesc('created_at')
            ->limit(50);

        // Institution admins see their own institution's alerts plus
        // platform-wide ones (unknown-user probes etc.).
        if (!$user->isPlatformAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('institution_id', $user->institution_id)
                    ->orWhereNull('institution_id');
            });
        }

        $status = $request->query('status');
        if ($status === 'unacknowledged') {
            $query->whereNull('acknowledged_at');
        } elseif ($status === 'acknowledged') {
            $query->whereNotNull('acknowledged_at');
        }

        $alerts = $query->get()->map(fn ($alert) => $this->payload($alert));

        return response()->json([
            'alerts' => $alerts,
            'unacknowledged_count' => SecurityAlert::whereNull('acknowledged_at')
                ->when(!$user->isPlatformAdmin(), fn ($q) => $q->where(function ($sub) use ($user) {
                    $sub->where('institution_id', $user->institution_id)
                        ->orWhereNull('institution_id');
                }))
                ->count(),
        ]);
    }

    public function acknowledge(Request $request, int $id)
    {
        $user = $request->user();
        abort_unless(
            $user?->isInstitutionAdmin() || $user?->isPlatformAdmin(),
            403,
            'Administrator access required.'
        );

        $alert = SecurityAlert::findOrFail($id);

        // Institution admins may acknowledge their own institution's alerts and
        // platform-wide ones (institution_id null), but never another institution's.
        if (!$user->isPlatformAdmin() && $alert->institution_id !== null
            && (int) $alert->institution_id !== (int) $user->institution_id) {
            abort(403, 'Security alert does not belong to your institution.');
        }

        if (!$alert->acknowledged_at) {
            $alert->acknowledged_at = now();
            $alert->acknowledged_by = $user->id;
            $alert->save();
        }

        return response()->json([
            'message' => 'Alert acknowledged.',
            'alert' => $this->payload($alert->fresh()),
        ]);
    }

    private function payload(SecurityAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'kind' => $alert->kind,
            'severity' => $alert->severity,
            'message' => $alert->message,
            'context' => $alert->context,
            'ip_address' => $alert->ip_address,
            'created_at' => $alert->created_at,
            'acknowledged_at' => $alert->acknowledged_at,
        ];
    }
}
