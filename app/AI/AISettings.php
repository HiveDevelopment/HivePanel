<?php

namespace App\AI;

use App\Support\AppSettings;
use Illuminate\Support\Facades\Crypt;

class AISettings
{
    public static function current(): array
    {
        $stored = AppSettings::get('ai');
        if (!array_key_exists('provider', $stored)) {
            return ['enabled' => config('ai.provider') !== 'disabled', 'provider' => config('ai.provider'), 'model' => config('ai.'.config('ai.provider').'.model', ''), 'url' => config('ai.ollama.url'), 'key' => config('ai.'.config('ai.provider').'.key')];
        }
        $key = '';
        if (!empty($stored['encrypted_key'])) {
            $key = Crypt::decryptString($stored['encrypted_key']);
        }
        return ['enabled' => (bool) ($stored['enabled'] ?? false), 'provider' => $stored['provider'], 'model' => $stored['model'], 'url' => $stored['url'] ?? 'http://127.0.0.1:11434', 'key' => $key];
    }

    public static function apply(): void
    {
        $settings = self::current();
        $provider = $settings['enabled'] ? $settings['provider'] : 'disabled';
        config(['ai.provider' => $provider]);
        if ($provider !== 'disabled') {
            config(["ai.{$provider}.model" => $settings['model']]);
            if ($provider === 'ollama') config(['ai.ollama.url' => $settings['url']]);
            else config(["ai.{$provider}.key" => $settings['key']]);
        }
    }
}
