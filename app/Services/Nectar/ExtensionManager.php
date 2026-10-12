<?php

namespace App\Services\Nectar;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class ExtensionManager
{
    private const MAX_ARCHIVE_BYTES = 20 * 1024 * 1024;
    private const MAX_UNPACKED_BYTES = 60 * 1024 * 1024;
    private const MAX_FILES = 150;

    // Keep this in sync with NectarAssetController's supported MIME types.
    private const ALLOWED = ['json', 'html', 'js', 'css', 'png', 'webp', 'woff2'];

    private const SLOTS = [
        'dashboard.widgets',
        'server.console.after',
        'server.overview.after',
    ];

    public function directory(): string
    {
        return storage_path('app/nectar/extensions');
    }

    public function installed(): array
    {
        return DB::table('nectar_extensions')->orderBy('extension_id')->get()->map(fn ($row) => [
            'id' => $row->extension_id,
            'name' => $row->name,
            'version' => $row->version,
            'enabled' => (bool) $row->enabled,
            'networkApproved' => (bool) ($row->network_approved ?? false),
            'manifest' => json_decode($row->manifest, true),
        ])->all();
    }

    public function validate(array $manifest): array
    {
        if (isset($manifest['capabilities'])) {
            if (isset($manifest['permissions']) && $manifest['permissions'] !== $manifest['capabilities']) {
                throw ValidationException::withMessages(['capabilities' => 'Use one matching capability declaration.']);
            }
            $manifest['permissions'] = $manifest['capabilities'];
            unset($manifest['capabilities']);
        }
        Validator::make($manifest, [
            'id' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9.-]{2,159}$/D'],
            'name' => ['required', 'string', 'max:100'],
            'version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/D'],
            'panelCompatibility' => ['required', 'string', Rule::in([(string) config('nectar.panel_version', 'dev')])],
            'frontend' => ['required', 'array'],
            'frontend.entry' => ['required', 'string', Rule::in(['dist/index.html'])],
            'frontend.slots' => ['sometimes', 'array', 'list', 'max:5'],
            'frontend.slots.*' => ['required', 'string', 'distinct', Rule::in(self::SLOTS)],
            'frontend.combs' => ['sometimes', 'array', 'list', 'max:50'],
            'frontend.combs.*' => ['required', 'string', 'distinct', 'regex:/^[a-zA-Z0-9_.:-]{1,120}$/D'],
            'events' => ['sometimes', 'array'],
            'events.subscribe' => ['sometimes', 'array', 'list', 'max:3'],
            'events.subscribe.*' => ['required', 'string', 'distinct', Rule::in(['cell.created', 'cell.updated', 'cell.deleted'])],
            'frontend.pages' => ['sometimes', 'array', 'list', 'max:10'],
            'frontend.pages.*' => ['required', 'array'],
            'frontend.pages.*.slug' => ['required', 'string', 'regex:/^[a-z][a-z0-9-]{0,47}$/D', 'distinct'],
            'frontend.pages.*.title' => ['required', 'string', 'max:60'],
            'frontend.pages.*.entry' => ['required', 'string', 'regex:/^dist\/pages\/[a-z][a-z0-9-]{0,47}\.html$/D'],
            'frontend.pages.*.navigation' => ['sometimes', 'boolean'],
            'backend' => ['prohibited'],
            'hooks' => ['prohibited'],
            'permissions' => ['sometimes', 'array', 'list', 'max:5'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(['cells.read', 'settings.read', 'network.http', 'network.tcp'])],
            'network' => ['sometimes', 'array:http,tcp'],
            'network.http' => ['sometimes', 'array:hosts'],
            'network.http.hosts' => ['required_with:network.http', 'array', 'list', 'min:1', 'max:20'],
            'network.http.hosts.*' => ['required', 'string', 'distinct', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D'],
            'network.tcp' => ['sometimes', 'array:scope'],
            'network.tcp.scope' => ['required_with:network.tcp', Rule::in(['cell-allocations'])],
            'settings' => ['sometimes', 'array', 'list', 'max:30'],
            'settings.*' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,47}$/D', 'distinct'],
            'settings.*.label' => ['required', 'string', 'max:100'],
            'settings.*.description' => ['sometimes', 'string', 'max:300'],
            'settings.*.type' => ['required', 'string', Rule::in(['text', 'number', 'toggle', 'select', 'password'])],
            'settings.*.default' => ['sometimes'],
            'settings.*.options' => ['sometimes', 'array', 'list', 'min:1', 'max:30'],
            'settings.*.options.*' => ['required', 'string', 'max:100', 'distinct'],
            'settings.*.min' => ['sometimes', 'numeric'],
            'settings.*.max' => ['sometimes', 'numeric'],
        ], [
            'panelCompatibility.in' => 'This extension targets a different HivePanel version.',
            'frontend.entry.in' => 'The frontend entry must be dist/index.html.',
            'frontend.slots.*.in' => 'The extension declares an unsupported UI slot.',
            'backend.prohibited' => 'Backend code is not supported by Nectar yet.',
            'hooks.prohibited' => 'Backend hooks are not supported by Nectar yet.',
            'permissions.*.in' => 'The extension requests an unsupported capability.',
        ])->validate();

        foreach (['http', 'tcp'] as $transport) {
            if (in_array("network.{$transport}", $manifest['permissions'] ?? [], true) !== isset($manifest['network'][$transport])) {
                throw ValidationException::withMessages(['network' => 'Each network capability must have its matching scope declaration.']);
            }
        }

        if (empty($manifest['frontend']['slots']) && empty($manifest['frontend']['pages'])) {
            throw ValidationException::withMessages([
                'frontend' => 'Declare at least one widget slot or extension page.',
            ]);
        }

        foreach ($manifest['frontend']['pages'] ?? [] as $page) {
            if ($page['entry'] !== "dist/pages/{$page['slug']}.html") {
                throw ValidationException::withMessages([
                    'frontend.pages' => 'Each page entry must match its slug.',
                ]);
            }
        }

        foreach ($manifest['settings'] ?? [] as $setting) {
            $type = $setting['type'];
            $key = $setting['key'];
            $options = $setting['options'] ?? [];
            if (($type === 'select') !== array_key_exists('options', $setting)) {
                throw ValidationException::withMessages(['settings' => "Invalid options for {$key}."]);
            }
            if ($type !== 'number' && (isset($setting['min']) || isset($setting['max']))) {
                throw ValidationException::withMessages(['settings' => "Only number settings support bounds: {$key}."]);
            }
            if (isset($setting['min'], $setting['max']) && $setting['min'] > $setting['max']) {
                throw ValidationException::withMessages(['settings' => "Invalid numeric bounds: {$key}."]);
            }
            if ($type === 'password' && array_key_exists('default', $setting)) {
                throw ValidationException::withMessages(['settings' => "Secret defaults are forbidden: {$key}."]);
            }
            if (array_key_exists('default', $setting)) {
                $default = $setting['default'];
                $valid = match ($type) {
                    'toggle' => is_bool($default),
                    'number' => (is_int($default) || is_float($default))
                        && (!isset($setting['min']) || $default >= $setting['min'])
                        && (!isset($setting['max']) || $default <= $setting['max']),
                    'select' => is_string($default) && in_array($default, $options, true),
                    default => is_string($default) && mb_strlen($default) <= 2000,
                };
                if (! $valid) {
                    throw ValidationException::withMessages(['settings' => "Invalid default for {$key}."]);
                }
            }
        }

        return $manifest;
    }

    public function install(string $archivePath): array
    {
        if (! is_file($archivePath) || filesize($archivePath) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('Archive exceeds 20 MB limit or does not exist');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZIP extension (ext-zip) is required to install Nectar packages.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Invalid .nectar ZIP archive');
        }

        $stage = storage_path('app/nectar/staging/'.bin2hex(random_bytes(12)));
        File::ensureDirectoryExists($stage);

        try {
            if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_FILES) {
                throw new RuntimeException('Invalid file count');
            }

            $total = 0;
            $seen = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (! is_array($stat) || ! isset($stat['name'], $stat['size'])) {
                    throw new RuntimeException('Invalid archive entry');
                }

                $name = $stat['name'];
                $isDirectory = str_ends_with($name, '/');
                $normalized = $isDirectory ? substr($name, 0, -1) : $name;

                if (
                    $normalized === ''
                    || str_contains($name, '\\')
                    || str_contains($name, "\0")
                    || str_starts_with($name, '/')
                    || str_contains($normalized, '//')
                    || preg_match('#(^|/)\.{1,2}(/|$)#', $normalized)
                    || ! preg_match('#^[a-zA-Z0-9_./-]+$#D', $normalized)
                ) {
                    throw new RuntimeException('Unsafe archive path');
                }

                // ZIPs can contain duplicate names; Windows filesystems also collide on case.
                $key = strtolower($normalized);
                if (isset($seen[$key])) {
                    throw new RuntimeException('Duplicate archive path: '.$name);
                }
                $seen[$key] = true;

                // Only nectar.json at the root and static files under dist/ are allowed.
                if ($normalized !== 'dist' && $normalized !== 'nectar.json' && ! str_starts_with($normalized, 'dist/')) {
                    throw new RuntimeException('File outside dist directory: '.$name);
                }
                if ($isDirectory && $normalized === 'nectar.json') {
                    throw new RuntimeException('Invalid manifest entry');
                }

                $opsys = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                    $mode = ($attributes >> 16) & 0170000;
                    $expected = $isDirectory ? 0040000 : 0100000;
                    if ($mode !== 0 && $mode !== $expected) {
                        throw new RuntimeException('Archive contains symlink or special file');
                    }
                }

                if ($isDirectory) {
                    continue;
                }

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (! in_array($ext, self::ALLOWED, true)) {
                    throw new RuntimeException('Disallowed file type: '.$name);
                }

                $size = (int) $stat['size'];
                if ($size < 0 || $size > self::MAX_UNPACKED_BYTES - $total) {
                    throw new RuntimeException('Unpacked size exceeds 60 MB');
                }
                $total += $size;

                $target = $stage.'/'.$name;
                File::ensureDirectoryExists(dirname($target));

                $input = $zip->getStream($name);
                if (! is_resource($input)) {
                    throw new RuntimeException('Cannot read archive member');
                }

                $output = fopen($target, 'xb');
                if (! is_resource($output)) {
                    fclose($input);
                    throw new RuntimeException('Cannot write staged file');
                }

                try {
                    // Bound actual extraction as well as trusting the ZIP metadata.
                    $written = stream_copy_to_stream($input, $output, $size + 1);
                } finally {
                    fclose($input);
                    fclose($output);
                }

                if ($written === false || $written !== $size) {
                    throw new RuntimeException('Archive member length mismatch');
                }
            }

            if (! is_file($stage.'/nectar.json') || ! is_file($stage.'/dist/index.html')) {
                throw new RuntimeException('Missing manifest or frontend entry');
            }

            $manifest = json_decode(File::get($stage.'/nectar.json'), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($manifest)) {
                throw new RuntimeException('Invalid manifest');
            }

            $this->validate($manifest);

            foreach ($manifest['frontend']['pages'] ?? [] as $page) {
                if (! is_file($stage.'/'.$page['entry'])) {
                    throw ValidationException::withMessages([
                        'frontend.pages' => "Missing page entry: {$page['entry']}",
                    ]);
                }
            }

            $id = $manifest['id'];
            $destination = $this->directory().'/'.$id;
            File::ensureDirectoryExists($this->directory());

            if (File::exists($destination) || DB::table('nectar_extensions')->where('extension_id', $id)->exists()) {
                throw new RuntimeException('Already installed: uninstall before replacing');
            }

            if (! rename($stage, $destination)) {
                throw new RuntimeException('Could not publish extension directory');
            }

            try {
                DB::table('nectar_extensions')->insert([
                    'extension_id' => $id,
                    'name' => $manifest['name'],
                    'version' => $manifest['version'],
                    'enabled' => false,
                    'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                File::deleteDirectory($destination);
                throw $e;
            }

            return $manifest;
        } finally {
            $zip->close();
            if (File::exists($stage)) {
                File::deleteDirectory($stage);
            }
        }
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        if (! DB::table('nectar_extensions')->where('extension_id', $id)->exists()) {
            throw new RuntimeException('Unknown extension');
        }

        $extension = DB::table('nectar_extensions')->where('extension_id', $id)->first();
        $manifest = json_decode($extension->manifest, true);
        if ($enabled && array_intersect(['network.http', 'network.tcp'], $manifest['permissions'] ?? []) && ! ($extension->network_approved ?? false)) {
            throw new RuntimeException('Approve the declared network permissions before enabling this extension.');
        }

        DB::table('nectar_extensions')->where('extension_id', $id)->update([
            'enabled' => $enabled,
            'updated_at' => now(),
        ]);
    }

    public function uninstall(string $id): void
    {
        $this->setEnabled($id, false);
        $directory = $this->directory().'/'.$id;

        if (is_dir($directory) && ! File::deleteDirectory($directory)) {
            throw new RuntimeException('Unable to delete extension files');
        }

        DB::table('nectar_extensions')->where('extension_id', $id)->delete();
    }

    public function activeForSlot(string $slot): array
    {
        return array_values(array_filter(
            $this->installed(),
            fn ($item) => $item['enabled']
                && in_array($slot, $item['manifest']['frontend']['slots'] ?? [], true)
        ));
    }

    public function enabledWithCapability(string $id, string $capability): bool
    {
        $extension = DB::table('nectar_extensions')
            ->where('extension_id', $id)
            ->where('enabled', true)
            ->first();

        if (! $extension) {
            return false;
        }

        $manifest = json_decode($extension->manifest, true);

        return is_array($manifest)
            && in_array($capability, $manifest['permissions'] ?? [], true)
            && (! str_starts_with($capability, 'network.') || ($extension->network_approved ?? false));
    }

    public function pages(): array
    {
        $pages = [];
        foreach ($this->installed() as $extension) {
            if (! $extension['enabled']) {
                continue;
            }

            foreach ($extension['manifest']['frontend']['pages'] ?? [] as $page) {
                $pages[] = [
                    'extensionId' => $extension['id'],
                    'extensionName' => $extension['name'],
                    'slug' => $page['slug'],
                    'title' => $page['title'],
                    'href' => route('nectar.pages.show', [
                        'id' => $extension['id'],
                        'slug' => $page['slug'],
                    ]),
                    'navigation' => $page['navigation'] ?? true,
                ];
            }
        }

        return $pages;
    }

    public function asset(string $id, string $path): string
    {
        if (! preg_match('/^[a-z0-9][a-z0-9.-]{2,159}$/D', $id)) {
            abort(404);
        }

        if (! DB::table('nectar_extensions')->where('extension_id', $id)->where('enabled', true)->exists()) {
            abort(404);
        }

        if (
            ! preg_match('#^dist/[a-zA-Z0-9_./-]+$#D', $path)
            || str_contains($path, '..')
            || str_contains($path, '//')
        ) {
            abort(404);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, self::ALLOWED, true)) {
            abort(404);
        }

        $root = realpath($this->directory().'/'.$id.'/dist');
        if ($root === false || ! is_dir($root) || is_link($this->directory().'/'.$id.'/dist')) {
            abort(404);
        }

        $relativePath = substr($path, strlen('dist/'));
        $candidate = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $resolved = realpath($candidate);
        if ($resolved === false || ! is_file($resolved)) {
            abort(404);
        }

        $prefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($resolved, $prefix)) {
            abort(404);
        }

        $current = $root;
        foreach (explode('/', $relativePath) as $segment) {
            $current .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($current)) {
                abort(404);
            }
        }

        if (! is_readable($resolved)) {
            abort(404);
        }

        return $resolved;
    }
}
