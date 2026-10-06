<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WateringLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'block_id', 'user_id', 'trigger_type', 'duration_seconds',
        'water_volume_liters', 'moisture_before', 'moisture_after',
        'note', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'duration_seconds' => 'float',
        'water_volume_liters' => 'float',
        'moisture_before' => 'float',
        'moisture_after' => 'float',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTriggerLabelAttribute(): string
    {
        return $this->trigger_type === 'auto' ? 'Otomatis' : 'Manual';
    }
}
