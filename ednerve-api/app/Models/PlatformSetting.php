<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'key',
        'group',
        'type',
        'label',
        'description',
        'value',
        'is_secret',
    ];

    protected $hidden = [
        'is_secret',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_secret' => 'boolean',
        ];
    }

    /** Decode the stored JSON payload into the declared type. */
    public function typedValue(): mixed
    {
        $value = $this->value;

        return match ($this->type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'json' => $value, // already decoded from JSON
            default => $value === null ? null : (string) $value,
        };
    }

    /** Coerce an incoming payload into the storage representation. */
    public static function putTypedValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            default => $value, // string / json pass through (cast at read)
        };
    }
}
