<?php

namespace App\Http\Controllers\Admin;

use App\AI\AIManager;
use App\AI\AISettings;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Support\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminAISettingsController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required','boolean'],
            'provider' => ['required','in:openai,openrouter,gemini,ollama'],
            'model' => ['required','string','max:120','regex:~^[a-zA-Z0-9_.:/-]+$~'],
            'api_key' => ['nullable','string','max:4096'],
            'clear_key' => ['sometimes','boolean'],
            'url' => ['nullable','string','max:255'],
        ]);
        $old = AppSettings::get('ai');
        $providers = $old['providers'] ?? [];
        if (!empty($old['provider']) && !isset($providers[$old['provider']])) {
            $providers[$old['provider']] = [
                'encrypted_key' => $old['encrypted_key'] ?? null,
                'model' => $old['model'] ?? '',
                'url' => $old['url'] ?? null,
            ];
        }
        $provider = $data['provider'];
        $entry = $providers[$provider] ?? [];
        $encrypted = ($data['clear_key'] ?? false) ? null : ($entry['encrypted_key'] ?? null);
        if (filled($data['api_key'] ?? null)) {
            $encrypted = Crypt::encryptString(trim($data['api_key']));
        }
        if ($data['enabled'] && $provider !== 'ollama' && !$encrypted) {
            throw ValidationException::withMessages(['api_key' => 'Enter an API key for this provider.']);
        }
        $url = $data['url'] ?: 'http://127.0.0.1:11434';
        if ($provider === 'ollama') {
            $parts = parse_url($url);
            $host = strtolower($parts['host'] ?? '');
            if (!in_array($parts['scheme'] ?? '', ['http','https'], true) || !in_array($host, ['localhost','127.0.0.1','::1'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                throw ValidationException::withMessages(['url' => 'Only local loopback Ollama URLs are supported.']);
            }
        }
        $providers[$provider] = ['encrypted_key' => $encrypted, 'model' => $data['model'], 'url' => $url];
        AppSetting::updateOrCreate(['key' => 'ai'], ['value' => [
            'enabled' => $data['enabled'], 'provider' => $provider,
            'model' => $data['model'], 'url' => $url, 'encrypted_key' => $encrypted,
            'providers' => $providers,
        ]]);
        AppSettings::clear('ai');
        return back()->with('success', 'Hive AI settings saved.');
    }

    public function models(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'in:gemini,openai,openrouter,ollama'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'url' => ['nullable', 'string', 'max:255'],
        ]);
        $provider = $data['provider'];
        $stored = AppSettings::get('ai');
        $providers = $stored['providers'] ?? [];
        $entry = $providers[$provider] ?? [];
        if (($stored['provider'] ?? '') === $provider) {
            $entry = array_merge(['encrypted_key' => $stored['encrypted_key'] ?? null, 'url' => $stored['url'] ?? null], $entry);
        }
        $key = trim($data['api_key'] ?? '');
        if ($key === '' && !empty($entry['encrypted_key'])) {
            $key = Crypt::decryptString($entry['encrypted_key']);
        }
        if ($provider !== 'ollama' && $key === '') {
            return response()->json(['message' => 'Enter an API key or save one for this provider first.'], 422);
        }
        $url = $data['url'] ?? ($entry['url'] ?? 'http://127.0.0.1:11434');
        if ($provider === 'ollama') {
            $parts = parse_url($url);
            if (!in_array($parts['scheme'] ?? '', ['http', 'https'], true)
                || !in_array(strtolower($parts['host'] ?? ''), ['127.0.0.1', 'localhost', '::1'], true)
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                return response()->json(['message' => 'Only loopback Ollama URLs are supported.'], 422);
            }
        }
        $rateKey = 'ai-models:'.$request->user()->getAuthIdentifier();
        if (RateLimiter::tooManyAttempts($rateKey, 12)) {
            return response()->json(['message' => 'Too many model requests. Try again shortly.'], 429);
        }
        RateLimiter::hit($rateKey, 60);
        try {
            $http = \Illuminate\Support\Facades\Http::timeout(15)->acceptJson();
            $response = match ($provider) {
                'gemini' => $http->withHeaders(['x-goog-api-key' => $key])->get('https://generativelanguage.googleapis.com/v1beta/models', ['pageSize' => 1000]),
                'openai' => $http->withToken($key)->get('https://api.openai.com/v1/models'),
                'openrouter' => $http->withToken($key)->get('https://openrouter.ai/api/v1/models'),
                'ollama' => $http->get(rtrim($url, '/').'/api/tags'),
            };
            if (!$response->successful()) {
                $message = data_get($response->json(), 'error.message') ?: 'Provider returned HTTP '.$response->status();
                return response()->json(['message' => mb_substr($message, 0, 300)], 422);
            }
            $items = match ($provider) {
                'gemini' => collect($response->json('models') ?? [])->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
                    ->map(fn ($m) => ['id' => preg_replace('~^models/~', '', $m['name'] ?? ''), 'name' => $m['displayName'] ?? ($m['name'] ?? '')]),
                'ollama' => collect($response->json('models') ?? [])->map(fn ($m) => ['id' => $m['name'] ?? '', 'name' => $m['name'] ?? '']),
                default => collect($response->json('data') ?? [])->map(fn ($m) => ['id' => $m['id'] ?? '', 'name' => $m['name'] ?? ($m['id'] ?? '')]),
            };
            return response()->json(['models' => $items->filter(fn ($m) => $m['id'] !== '')->sortBy('id')->values()->all()]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not retrieve models. Check provider connectivity and server logs.'], 422);
        }
    }

    public function test(Request $request)
    {
        $key = 'ai-test:'.$request->user()->getAuthIdentifier();
        if (RateLimiter::tooManyAttempts($key, 5)) return response()->json(['message' => 'Too many tests. Try again later.'], 429);
        RateLimiter::hit($key, 60);
        try {
            if (!AISettings::current()['enabled']) return response()->json(['message' => 'Enable Hive AI before testing.'], 422);
            $answer = app(AIManager::class)->text('You are Hive AI. Reply briefly.', 'Reply with: Hive AI connection successful.');
            return response()->json(['message' => 'Connection successful.', 'answer' => mb_substr($answer, 0, 300)]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => $e instanceof \RuntimeException ? $e->getMessage() : 'AI connection failed. Check the server logs.'], 422);
        }
    }
}
