<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class HivePanelUpdateService
{
    public function currentVersion(): string
    {
        return ltrim((string) config('hivepanel.version', 'development'), 'v');
    }

    public function latestRelease(): ?array
    {
        return Cache::remember('hivepanel:update:latest', now()->addHour(), function () {
            $repository = config('hivepanel.repository');

            $response = Http::acceptJson()
                ->withUserAgent('HivePanel/'. $this->currentVersion())
                ->timeout(10)
                ->get("https://api.github.com/repos/{$repository}/releases/latest");

            if ($response->status() === 404) {
                return null;
            }

            $response->throw();
            $release = $response->json();
            $version = ltrim((string) ($release['tag_name'] ?? ''), 'v');

            if (! $this->validVersion($version)) {
                return null;
            }

            return [
                'version' => $version,
                'name' => $release['name'] ?? "HivePanel {$version}",
                'body' => $release['body'] ?? '',
                'published_at' => $release['published_at'] ?? null,
                'url' => $release['html_url'] ?? null,
                'available' => version_compare($version, $this->currentVersion(), '>'),
            ];
        });
    }

    public function status(): array
    {
        if (is_file(config('hivepanel.update_request_path'))) {
            $request = json_decode((string) file_get_contents(config('hivepanel.update_request_path')), true);

            return [
                'state' => 'queued',
                'version' => is_array($request) ? ($request['version'] ?? null) : null,
                'message' => 'Update approved and waiting for the host updater.',
            ];
        }

        $path = config('hivepanel.update_status_path');

        if (! is_file($path)) {
            return ['state' => 'idle'];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : ['state' => 'idle'];
    }

    public function request(string $version, int|string $userId): void
    {
        $version = ltrim(trim($version), 'v');

        if (! $this->validVersion($version)) {
            throw new RuntimeException('The requested HivePanel version is invalid.');
        }

        $latest = $this->latestRelease();

        if (! $latest || $latest['version'] !== $version || ! $latest['available']) {
            throw new RuntimeException('That HivePanel release is not available for installation.');
        }

        $path = config('hivepanel.update_request_path');
        $directory = dirname($path);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException('The host updater is not available on this installation.');
        }

        $payload = json_encode([
            'id' => (string) Str::uuid(),
            'version' => $version,
            'requested_by' => (string) $userId,
            'requested_at' => now()->toISOString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $temporary = $path.'.tmp';
        file_put_contents($temporary, $payload, LOCK_EX);
        rename($temporary, $path);
    }

    private function validVersion(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+(?:-(?:alpha|beta|rc)(?:\.\d+)?)?$/', $version) === 1;
    }
}
