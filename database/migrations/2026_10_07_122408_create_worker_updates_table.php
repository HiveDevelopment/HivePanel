<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_updates', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('node_id')
                ->constrained('nodes')
                ->cascadeOnDelete();

            $table->foreignUuid('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('from_version', 100)->nullable();
            $table->string('target_version', 100);

            $table->string('status', 32)
                ->default('queued');

            $table->text('error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index([
                'node_id',
                'status',
            ]);

            $table->index([
                'status',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_updates');
    }
};