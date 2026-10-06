<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SensorReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'block_id', 'soil_moisture', 'temperature', 'humidity',
        'light_intensity', 'ph_level', 'nitrogen', 'phosphorus',
        'potassium', 'is_raining', 'recorded_at',
    ];

    protected $casts = [
        'soil_moisture' => 'float',
        'temperature' => 'float',
        'humidity' => 'float',
        'light_intensity' => 'float',
        'ph_level' => 'float',
        'nitrogen' => 'float',
        'phosphorus' => 'float',
        'potassium' => 'float',
        'is_raining' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    public function getMoistureStatusAttribute(): string
    {
        if ($this->soil_moisture < 30) return 'critical';
        if ($this->soil_moisture < 50) return 'warning';
        return 'normal';
    }
}
