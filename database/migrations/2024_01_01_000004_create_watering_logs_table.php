<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watering_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('trigger_type', ['manual', 'auto'])->default('manual');
            $table->float('duration_seconds')->default(30);
            $table->float('water_volume_liters')->nullable();
            $table->float('moisture_before')->nullable();
            $table->float('moisture_after')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['block_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watering_logs');
    }
};
