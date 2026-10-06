<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name');             // e.g. "Blok A - Wortel"
            $table->string('vegetable');        // wortel | tomat | bayam
            $table->string('emoji')->default('🌱');
            $table->string('location')->nullable();
            $table->float('area_m2')->default(100);
            $table->float('moisture_threshold')->default(50); // % below this → auto water
            $table->float('temp_min')->default(18);
            $table->float('temp_max')->default(28);
            $table->float('ph_min')->default(6.0);
            $table->float('ph_max')->default(7.0);
            $table->boolean('auto_water_enabled')->default(true);
            $table->enum('status', ['normal', 'warning', 'critical', 'watering'])->default('normal');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
