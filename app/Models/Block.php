<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'vegetable', 'emoji', 'location', 'area_m2',
        'moisture_threshold', 'temp_min', 'temp_max', 'ph_min', 'ph_max',
        'auto_water_enabled', 'status', 'description',
    ];

    protected $casts = [
        'auto_water_enabled' => 'boolean',
        'area_m2' => 'float',
        'moisture_threshold' => 'float',
        'temp_min' => 'float',
        'temp_max' => 'float',
        'ph_min' => 'float',
        'ph_max' => 'float',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function sensorReadings()
    {
        return $this->hasMany(SensorReading::class)->orderByDesc('recorded_at');
    }

    public function latestReading()
    {
        return $this->hasOne(SensorReading::class)->latestOfMany('recorded_at');
    }

    public function wateringLogs()
    {
        return $this->hasMany(WateringLog::class)->orderByDesc('started_at');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function needsWater(): bool
    {
        $latest = $this->latestReading;
        if (!$latest) return false;
        return $latest->soil_moisture < $this->moisture_threshold;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'normal'   => 'green',
            'warning'  => 'yellow',
            'critical' => 'red',
            'watering' => 'blue',
            default    => 'gray',
        };
    }

    public function getVegetableDisplayAttribute(): string
    {
        return match ($this->vegetable) {
            'wortel' => 'Wortel',
            'tomat'  => 'Tomat',
            'bayam'  => 'Bayam',
            default  => ucfirst($this->vegetable),
        };
    }
}
