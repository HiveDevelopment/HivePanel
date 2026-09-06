<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CellDatabase extends Model
{
    use HasUuids;

    protected $fillable = [
        'cell_id',
        'database_host_id',
        'database_name',
        'username',
        'password',
        'allowed_host',
        'charset',
        'collation',
        'managed',
        'source',
        'source_reference',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'managed' => 'boolean',
        ];
    }

    public function cell(): BelongsTo
    {
        return $this->belongsTo(Cell::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(
            DatabaseHost::class,
            'database_host_id',
        );
    }
}