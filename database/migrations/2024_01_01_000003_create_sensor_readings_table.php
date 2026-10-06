<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->onDelete('cascade');
            $table->float('soil_moisture');     // %
            $table->float('temperature');       // °C
            $table->float('humidity');          // % air humidity
            $table->float('light_intensity');   // lux
            $table->float('ph_level');          // soil pH
            $table->float('nitrogen')->nullable();   // ppm
            $table->float('phosphorus')->nullable(); // ppm
            $table->float('potassium')->nullable();  // ppm
            $table->boolean('is_raining')->default(false);
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['block_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_readings');
    }
};
