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
        $providerChanged = ($old['provider'] ?? null) !== $data['provider'];
        $encrypted = $providerChanged || ($data['clear_key'] ?? false) ? null : ($old['encrypted_key'] ?? null);
        if (filled($data['api_key'] ?? null)) $encrypted = Crypt::encryptString(trim($data['api_key']));
        if ($data['enabled'] && $data['provider'] !== 'ollama' && !$encrypted) {
            throw ValidationException::withMessages(['api_key' => 'Enter an API key for this provider.']);
        }
        $url = $data['url'] ?: 'http://127.0.0.1:11434';
        if ($data['provider'] === 'ollama') {
            $parts = parse_url($url);
            $host = strtolower($parts['host'] ?? '');
            if (!in_array($parts['scheme'] ?? '', ['http','https'], true) || !in_array($host, ['localhost','127.0.0.1','::1'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                throw ValidationException::withMessages(['url' => 'For security, Ollama must use a local loopback address. Use a local reverse proxy or tunnel for remote inference.']);
            }
        }
        AppSetting::updateOrCreate(['key' => 'ai'], ['value' => [
            'enabled' => $data['enabled'], 'provider' => $data['provider'],
            'model' => $data['model'], 'url' => $url, 'encrypted_key' => $encrypted,
        ]]);
        AppSettings::clear('ai');
        return back()->with('success', 'Hive AI settings saved.');
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
