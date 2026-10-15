<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CellDomain extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['https' => 'boolean', 'redirect' => 'boolean', 'public_port' => 'integer', 'max_body_mb' => 'integer'];
    }
}
