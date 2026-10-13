<?php

namespace App\Services\Cells;

class CellConfigFiles
{
    public function declared(array $manifest): array
    {
        $configured = $manifest['config_files'] ?? null;
        if (is_array($configured)) {
            $files = [];
            foreach (array_slice($configured, 0, 50) as $file) {
                $path = is_string($file) ? $file : ($file['path'] ?? '');
                if (!$this->validPath($path)) continue;
                $files[] = ['id' => $path, 'path' => $path, 'title' => is_array($file) ? (string) ($file['title'] ?? basename($path)) : basename($path), 'description' => is_array($file) ? (string) ($file['description'] ?? 'Comb configuration file.') : 'Comb configuration file.'];
            }
            return $files;
        }
        if (strtolower((string) (($manifest['game'] ?? '') ?: ($manifest['group'] ?? ''))) !== 'minecraft') return [];
        return array_map(fn ($path) => ['id' => $path, 'path' => $path, 'title' => basename($path), 'description' => 'Minecraft configuration.'], ['server.properties', 'spigot.yml', 'bukkit.yml', 'config/paper-global.yml', 'config/paper-world-defaults.yml']);
    }

    public function discovered(array $entries): array
    {
        $files = [];
        foreach ($entries as $entry) {
            $path = $entry['name'] ?? '';
            if (!empty($entry['is_dir']) || !$this->validPath($path) || !preg_match('/\.(properties|json|ini|cfg|conf|yaml|yml|toml)$/i', $path) || in_array($path, ['package.json', 'package-lock.json', 'composer.json', 'composer.lock'], true)) continue;
            $files[] = ['id' => $path, 'path' => $path, 'title' => basename($path), 'description' => 'Configuration file found in this Cell.'];
        }
        return array_slice($files, 0, 50);
    }

    private function validPath(mixed $path): bool
    {
        return is_string($path) && $path !== '' && strlen($path) <= 512 && !str_starts_with($path, '/') && !str_contains($path, '..') && !str_contains($path, chr(92)) && !preg_match('/[\x00-\x1f]/', $path);
    }
}
