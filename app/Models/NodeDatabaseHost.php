<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeDatabaseHost extends Model
{
    use HasUuids;

    protected $fillable = [
        'node_id',
        'database_host_id',
        'priority',
        'is_primary',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_primary' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function databaseHost(): BelongsTo
    {
        return $this->belongsTo(DatabaseHost::class);
    }
}