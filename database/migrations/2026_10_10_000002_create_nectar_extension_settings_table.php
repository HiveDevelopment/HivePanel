<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nectar_extension_settings', function (Blueprint $table) {
            $table->id();
            $table->string('extension_id', 160);
            $table->string('setting_key', 48);
            $table->text('value');
            $table->timestamps();
            $table->unique(['extension_id', 'setting_key']);
            $table->foreign('extension_id')->references('extension_id')->on('nectar_extensions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nectar_extension_settings');
    }
};
