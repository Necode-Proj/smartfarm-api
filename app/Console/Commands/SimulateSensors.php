<?php

namespace App\Console\Commands;

use App\Models\Block;
use App\Models\SensorReading;
use App\Models\WateringLog;
use Illuminate\Console\Command;

class SimulateSensors extends Command
{
    protected $signature = 'sensors:simulate';
    protected $description = 'Generate realistic sensor readings for all blocks (run via scheduler every minute)';

    private array $veggieConfig = [
        'wortel' => ['temp_base' => 21, 'moisture_decay' => 1.5],
        'tomat'  => ['temp_base' => 23, 'moisture_decay' => 2.0],
        'bayam'  => ['temp_base' => 18, 'moisture_decay' => 2.5],
    ];

    public function handle(): void
    {
        $blocks = Block::with('latestReading')->get();
        $hour = now()->hour;
        $isDay = $hour >= 6 && $hour <= 18;

        foreach ($blocks as $block) {
            $prev = $block->latestReading;
            $cfg = $this->veggieConfig[$block->vegetable] ?? $this->veggieConfig['wortel'];

            // Decay moisture slightly each tick
            $prevMoisture = $prev?->soil_moisture ?? $block->moisture_threshold + 10;
            $decayRate = $isDay ? $cfg['moisture_decay'] : $cfg['moisture_decay'] * 0.3;
            $moisture = max(10, $prevMoisture - ($decayRate * (rand(80, 120) / 100)));

            // Temperature with day/night variation
            $temp = $cfg['temp_base']
                + ($isDay ? rand(0, 30) / 10 : -rand(0, 20) / 10)
                + (rand(-10, 10) / 10);

            $humidity = $isDay
                ? ($prev?->humidity ?? 65) + rand(-5, 3)
                : ($prev?->humidity ?? 70) + rand(-3, 5);

            SensorReading::create([
                'block_id'        => $block->id,
                'soil_moisture'   => round(max(10, min(95, $moisture)), 1),
                'temperature'     => round(max(10, min(40, $temp)), 1),
                'humidity'        => round(max(30, min(98, $humidity)), 1),
                'light_intensity' => $isDay ? rand(2000, 9000) : rand(0, 150),
                'ph_level'        => round($block->ph_min + (rand(0, 100) / 100) * ($block->ph_max - $block->ph_min), 2),
                'nitrogen'        => round(rand(30, 80), 1),
                'phosphorus'      => round(rand(20, 60), 1),
                'potassium'       => round(rand(40, 100), 1),
                'is_raining'      => rand(0, 100) < 5, // 5% chance
                'recorded_at'     => now(),
            ]);

            // Auto-watering check
            if ($block->auto_water_enabled && $moisture < $block->moisture_threshold) {
                $newMoisture = min(90, $moisture + rand(20, 30));

                WateringLog::create([
                    'block_id'            => $block->id,
                    'user_id'             => null,
                    'trigger_type'        => 'auto',
                    'duration_seconds'    => rand(20, 45),
                    'water_volume_liters' => rand(5, 15),
                    'moisture_before'     => round($moisture, 1),
                    'moisture_after'      => round($newMoisture, 1),
                    'note'                => "Auto-siram: moisture turun ke {$moisture}% (threshold: {$block->moisture_threshold}%)",
                    'started_at'          => now(),
                    'ended_at'            => now()->addSeconds(rand(20, 45)),
                ]);

                // Insert post-watering reading
                SensorReading::create([
                    'block_id'        => $block->id,
                    'soil_moisture'   => round($newMoisture, 1),
                    'temperature'     => round($temp, 1),
                    'humidity'        => round($humidity, 1),
                    'light_intensity' => $isDay ? rand(2000, 9000) : rand(0, 150),
                    'ph_level'        => round($block->ph_min + (rand(0, 100) / 100) * ($block->ph_max - $block->ph_min), 2),
                    'nitrogen'        => round(rand(30, 80), 1),
                    'phosphorus'      => round(rand(20, 60), 1),
                    'potassium'       => round(rand(40, 100), 1),
                    'is_raining'      => false,
                    'recorded_at'     => now()->addSeconds(35),
                ]);

                $block->update(['status' => 'normal']);
                $this->info("💧 Auto-watered: {$block->name}");
            } else {
                // Update status
                $status = 'normal';
                if ($moisture < 25) $status = 'critical';
                elseif ($moisture < $block->moisture_threshold) $status = 'warning';
                $block->update(['status' => $status]);
            }

            // Prune old readings (keep last 7 days)
            SensorReading::where('block_id', $block->id)
                ->where('recorded_at', '<', now()->subDays(7))
                ->delete();
        }

        $this->info('✅ Sensor readings updated for all blocks.');
    }
}
