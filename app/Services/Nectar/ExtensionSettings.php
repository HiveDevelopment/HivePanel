<?php

namespace App\Services\Nectar;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExtensionSettings
{
    public function schema(string $id): array
    {
        $row = DB::table('nectar_extensions')->where('extension_id', $id)->first();
        abort_unless($row, 404);
        $manifest = json_decode($row->manifest, true);
        return $manifest['settings'] ?? [];
    }

    public function values(string $id, bool $publicOnly = false): array
    {
        $schema = $this->schema($id);
        $stored = DB::table('nectar_extension_settings')->where('extension_id', $id)
            ->pluck('value', 'setting_key')->all();
        $values = [];
        foreach ($schema as $setting) {
            $key = $setting['key'];
            if ($setting['type'] === 'password') {
                if (! $publicOnly) {
                    $values[$key] = ['configured' => isset($stored[$key])];
                }
                continue;
            }
            $values[$key] = isset($stored[$key])
                ? json_decode($stored[$key], true, 32, JSON_THROW_ON_ERROR)
                : ($setting['default'] ?? match ($setting['type']) {
                    'toggle' => false, 'number' => 0, default => '',
                });
        }
        return $values;
    }

    public function save(string $id, array $values): void
    {
        $schema = $this->schema($id);
        $rules = [];
        foreach ($schema as $setting) {
            $key = $setting['key'];
            $type = $setting['type'];
            $rule = match ($type) {
                'toggle' => ['required', 'boolean'],
                'number' => ['required', 'numeric'],
                'select' => ['required', 'string', \Illuminate\Validation\Rule::in($setting['options'])],
                'password' => ['nullable', 'string', 'max:2000'],
                default => ['required', 'string', 'max:2000'],
            };
            if ($type === 'number') {
                if (isset($setting['min'])) $rule[] = 'min:'.$setting['min'];
                if (isset($setting['max'])) $rule[] = 'max:'.$setting['max'];
            }
            $rules[$key] = $rule;
        }
        $validated = Validator::make($values, $rules)->validate();
        if (array_diff(array_keys($values), array_keys($rules))) {
            throw ValidationException::withMessages(['settings' => 'Unknown setting key.']);
        }
        DB::transaction(function () use ($id, $schema, $validated) {
            foreach ($schema as $setting) {
                $key = $setting['key'];
                $value = $validated[$key];
                if ($setting['type'] === 'password') {
                    // Empty means keep the existing secret; never echo it to the UI.
                    if ($value === null || $value === '') continue;
                    $encoded = Crypt::encryptString($value);
                } else {
                    $encoded = json_encode($value, JSON_THROW_ON_ERROR);
                }
                DB::table('nectar_extension_settings')->updateOrInsert(
                    ['extension_id' => $id, 'setting_key' => $key],
                    ['value' => $encoded, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });
    }
}
