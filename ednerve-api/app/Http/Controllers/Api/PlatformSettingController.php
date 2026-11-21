<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\PlatformSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PlatformSettingController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * All platform settings, grouped in the response.
     */
    public function index(Request $request)
    {
        $this->ensurePlatformAdmin($request);

        return response()->json([
            'settings' => $this->grouped(),
        ]);
    }

    /**
     * Bulk update of platform settings. Only existing keys may be set,
     * and values must match the declared `type`.
     */
    public function update(UpdateSettingsRequest $request)
    {
        $this->ensurePlatformAdmin($request);

        $payload = collect($request->validated()['settings'])
            ->keyBy('key');

        $settings = PlatformSetting::whereIn('key', $payload->keys())->get();

        $errors = [];
        $changed = [];

        foreach ($settings as $setting) {
            $item = $payload->get($setting->key);
            $value = $item['value'] ?? null;

            $castOk = match ($setting->type) {
                'boolean' => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true),
                'integer' => is_int($value) || (is_string($value) && ctype_digit($value)),
                'json' => is_array($value),
                default => is_string($value) || is_numeric($value),
            };

            if (!$castOk) {
                $errors["settings.{$setting->key}"] = ["Value must match the declared type ({$setting->type})."];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($settings as $setting) {
            $item = $payload->get($setting->key);
            $value = $this->coerceValue($setting->type, $item['value']);
            $new = ['value' => $value];

            $setting->fill($new);

            if ($setting->isDirty('value')) {
                $changed[$setting->key] = $value;
                $setting->save();
            }
        }

        if ($changed !== []) {
            $this->audit->record(
                $request,
                event: 'settings.updated',
                module: 'platform',
                newValues: $changed,
                description: 'Platform settings updated.',
            );
        }

        return response()->json([
            'message' => $changed === []
                ? 'No changes detected.'
                : 'Settings updated successfully.',
            'settings' => $this->grouped(),
        ]);
    }

    private function grouped(): Collection
    {
        return PlatformSetting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->map(fn (Collection $items, string $group) => [
                'group' => $group,
                'items' => $items->map(fn (PlatformSetting $setting) => [
                    'id' => $setting->id,
                    'key' => $setting->key,
                    'group' => $setting->group,
                    'type' => $setting->type,
                    'label' => $setting->label,
                    'description' => $setting->description,
                    'value' => $setting->typedValue(),
                ])->values(),
            ])
            ->values();
    }

    private function coerceValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            default => $value,
        };
    }
}
