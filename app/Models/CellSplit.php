<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CellSplit extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['recovery'];

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['resources' => 'array', 'recovery' => 'encrypted:array'];
    }
}
