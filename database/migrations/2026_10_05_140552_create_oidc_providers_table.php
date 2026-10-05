<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oidc_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('issuer', 2048);
            $table->string('client_id');
            $table->text('client_secret');
            $table->string('redirect_url', 2048)->nullable();
            $table->json('scopes')->nullable();
            $table->boolean('allow_registration')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oidc_providers');
    }
};