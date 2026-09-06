<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatabaseHost extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'driver',
        'host',
        'port',
        'username',
        'password',
        'public_host',
        'public_port',
        'max_databases',
        'enabled',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'port' => 'integer',
            'public_port' => 'integer',
            'max_databases' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    public function databases(): HasMany
    {
        return $this->hasMany(CellDatabase::class);
    }

    public function connectionHost(): string
    {
        return $this->host;
    }

    public function displayHost(): string
    {
        return $this->public_host ?: $this->host;
    }

    public function displayPort(): int
    {
        return $this->public_port ?: $this->port;
    }
}