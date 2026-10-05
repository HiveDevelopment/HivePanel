<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminCommand extends Command
{
    protected $signature = 'hivepanel:create-admin
        {--name= : Administrator name}
        {--email= : Administrator email address}
        {--password= : Administrator password}';

    protected $description = 'Create or promote a HivePanel administrator.';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: env('HIVEPANEL_ADMIN_NAME') ?: $this->ask('Administrator name')));
        $email = Str::lower(trim((string) ($this->option('email') ?: env('HIVEPANEL_ADMIN_EMAIL') ?: $this->ask('Administrator email'))));
        $password = (string) ($this->option('password') ?: env('HIVEPANEL_ADMIN_PASSWORD') ?: $this->secret('Administrator password'));

        if ($name === '') {
            $this->error('Administrator name is required.');
            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid administrator email address is required.');
            return self::FAILURE;
        }

        if (strlen($password) < 8) {
            $this->error('Administrator password must be at least 8 characters.');
            return self::FAILURE;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            $user->forceFill([
                'name' => $name,
                'is_admin' => true,
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();

            $this->info("Promoted {$email} to administrator.");
            return self::SUCCESS;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
            'is_admin' => true,
        ])->save();

        $this->info("Created administrator {$email}.");
        return self::SUCCESS;
    }
}
