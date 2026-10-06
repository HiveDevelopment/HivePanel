<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUuids, PasskeyAuthenticatable;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'is_admin',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'can_update_panel' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactorAuthenticationEnabled(): bool
    {
        return filled($this->two_factor_secret)
            && $this->two_factor_confirmed_at !== null;
    }


    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasAdminAccess(): bool
    {
        return $this->is_admin || $this->roles()->exists();
    }

    public function hasAdminPermission(string $permission): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }

    public function adminPermissions(): array
    {
        if ($this->is_admin) {
            return \App\Support\AdminPermissions::all();
        }

        return \App\Models\Permission::query()
            ->whereHas('roles.users', fn ($query) => $query->where('users.id', $this->id))
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    public function cells()
    {
        return $this->hasMany(\App\Models\Cell::class, 'owner_id');
    }

    public function cellAccess()
    {
        return $this->hasMany(\App\Models\CellUser::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(\App\Models\AuditLog::class);
    }

    public function sftpCredentials()
    {
        return $this->hasMany(\App\Models\SftpCredential::class);
    }
}