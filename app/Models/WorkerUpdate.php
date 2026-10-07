<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerUpdate extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_DISPATCHING = 'dispatching';
    public const STATUS_RESTARTING = 'restarting';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'node_id',
        'requested_by',
        'from_version',
        'target_version',
        'status',
        'error',
        'started_at',
        'dispatched_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isPending(): bool
    {
        return in_array($this->status, [
            self::STATUS_QUEUED,
            self::STATUS_DISPATCHING,
            self::STATUS_RESTARTING,
        ], true);
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETE;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}