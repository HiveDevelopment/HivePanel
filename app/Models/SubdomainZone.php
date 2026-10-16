<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SubdomainZone extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $hidden = ['api_token'];

    protected function casts(): array
    {
        return ['api_token' => 'encrypted', 'enabled' => 'boolean', 'reserved_labels' => 'array'];
    }
}
