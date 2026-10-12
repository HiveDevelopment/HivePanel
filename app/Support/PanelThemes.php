<?php

namespace App\Support;

use App\Models\AppSetting;

class PanelThemes
{
    public static function catalog(): array
    {
        return AppSetting::where('key', 'themes')->first()?->value ?? [
            'default' => 'hive',
            'themes' => array_map(fn ($id) => ['id' => $id, 'name' => ucfirst($id), 'enabled' => true, 'tokens' => json_decode(file_get_contents(resource_path("themes/{$id}.json")), true)], ['hive', 'ocean', 'forest']),
        ];
    }

    public static function offered(): array
    {
        $catalog = self::catalog();
        return ['default' => $catalog['default'], 'themes' => array_values(array_filter($catalog['themes'], fn ($theme) => $theme['enabled']))];
    }
}
