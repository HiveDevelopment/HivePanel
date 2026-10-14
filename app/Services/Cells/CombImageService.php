<?php

namespace App\Services\Cells;

use Illuminate\Validation\ValidationException;

class CombImageService
{
    public function options(array $manifest): array
    {
        $options = [];
        foreach ((array) ($manifest['docker_images'] ?? []) as $label => $image) {
            if (is_string($image) && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.\/_:@-]{1,250}$/', $image)) {
                $options[$image] = ['label' => is_string($label) ? $label : $image, 'image' => $image];
            }
        }
        $default = data_get($manifest, 'docker.image', $manifest['image'] ?? null);
        if (is_string($default) && $default !== '') {
            $options[$default] ??= ['label' => 'Default', 'image' => $default];
        }

        return array_values($options);
    }

    public function choose(array $manifest, ?string $requested, ?string $existing = null): string
    {
        $offered = array_column($this->options($manifest), 'image');
        $selected = $requested ?? (in_array($existing, $offered, true) ? $existing : (data_get($manifest, 'docker.image', $manifest['image'] ?? null) ?? $offered[0] ?? ''));
        if (! in_array($selected, $offered, true)) {
            throw ValidationException::withMessages(['image' => 'Choose an image offered by this Comb.']);
        }

        return $selected;
    }
}
