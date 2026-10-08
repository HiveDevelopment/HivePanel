<?php

namespace App\Support;

use App\Models\AppSetting;

class InvitationTemplate
{
    public static function settings(): array
    {
        $defaults = [
            'subject' => 'You have been invited to {app_name}',
            'greeting' => 'Welcome to {app_name}, {name}!',
            'message' => 'An administrator has created an account for you. Use the secure link below to set your password.',
            'button_text' => 'Set your password',
            'footer' => 'If you were not expecting this invitation, you can ignore this email.',
        ];

        return array_merge($defaults, AppSetting::query()->where('key', 'invitation_template')->value('value') ?? []);
    }

    public static function render(string $value, object $user): string
    {
        $appName = (string) (AppSettings::get('general')['company_name'] ?? config('app.name', 'HivePanel'));

        return strtr($value, [
            '{name}' => (string) $user->name,
            '{email}' => (string) $user->email,
            '{app_name}' => $appName,
        ]);
    }
}
