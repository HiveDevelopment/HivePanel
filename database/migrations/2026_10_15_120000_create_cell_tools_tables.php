<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cell_splits', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('parent_id')->constrained('cells')->restrictOnDelete();
            $t->foreignUuid('child_id')->unique()->constrained('cells')->restrictOnDelete();
            $t->json('resources');
            $t->text('recovery')->nullable();
            $t->string('state')->default('active');
            $t->text('error')->nullable();
            $t->timestamps();
        });
        Schema::create('subdomain_zones', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('domain', 253)->unique();
            $t->string('provider')->default('cloudflare');
            $t->string('provider_zone_id', 64);
            $t->text('api_token');
            $t->boolean('enabled')->default(true);
            $t->json('reserved_labels')->nullable();
            $t->timestamps();
        });
        Schema::create('cell_subdomains', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('zone_id')->constrained('subdomain_zones')->restrictOnDelete();
            $t->foreignUuid('cell_id')->constrained('cells')->restrictOnDelete();
            $t->uuid('allocation_id');
            $t->string('hostname', 253)->unique();
            $t->string('kind');
            $t->json('records')->nullable();
            $t->string('state')->default('pending');
            $t->text('error')->nullable();
            $t->uuid('route_id')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cell_subdomains');
        Schema::dropIfExists('subdomain_zones');
        Schema::dropIfExists('cell_splits');
    }
};
