<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\SensorReading;
use App\Models\User;
use App\Models\WateringLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    // Vegetable-specific sensor configs
    private array $veggieConfig = [
        'wortel' => [
            'emoji' => '🥕',
            'moisture_threshold' => 40,
            'moisture_base' => 45, // baseline soil moisture
            'temp_min' => 18, 'temp_max' => 24,
            'ph_min' => 6.0, 'ph_max' => 6.8,
            'description' => 'Blok tanaman wortel organik varietas Chantenay. Cocok untuk tanah berpasir.',
            'location' => 'Lahan A - Barat',
        ],
        'tomat' => [
            'emoji' => '🍅',
            'moisture_threshold' => 50,
            'moisture_base' => 55,
            'temp_min' => 20, 'temp_max' => 27,
            'ph_min' => 6.0, 'ph_max' => 6.8,
            'description' => 'Blok tanaman tomat cherry merah. Varietas Cantaloupe tahan penyakit.',
            'location' => 'Lahan B - Tengah',
        ],
        'bayam' => [
            'emoji' => '🌿',
            'moisture_threshold' => 60,
            'moisture_base' => 65,
            'temp_min' => 15, 'temp_max' => 22,
            'ph_min' => 6.5, 'ph_max' => 7.0,
            'description' => 'Blok tanaman bayam hijau varietas Malabar. Tumbuh cepat dan kaya nutrisi.',
            'location' => 'Lahan C - Timur',
        ],
    ];

    public function run(): void
    {
        // ── Users ─────────────────────────────────────────────────────────────
        $admin = User::updateOrCreate(
            ['email' => 'admin@smartfarm.id'],
            ['name' => 'Admin SmartFarm', 'password' => Hash::make('password'), 'role' => 'admin']
        );
        $op1 = User::updateOrCreate(
            ['email' => 'budi@smartfarm.id'],
            ['name' => 'Budi Santoso', 'password' => Hash::make('password'), 'role' => 'operator']
        );
        $op2 = User::updateOrCreate(
            ['email' => 'siti@smartfarm.id'],
            ['name' => 'Siti Rahayu', 'password' => Hash::make('password'), 'role' => 'operator']
        );

        $users = [$admin, $op1, $op2];

        // ── Blocks ─────────────────────────────────────────────────────────────
        $blockDefs = [
            ['name' => 'Blok A - Wortel', 'vegetable' => 'wortel'],
            ['name' => 'Blok B - Tomat',  'vegetable' => 'tomat'],
            ['name' => 'Blok C - Bayam',  'vegetable' => 'bayam'],
        ];

        foreach ($blockDefs as $def) {
            $cfg = $this->veggieConfig[$def['vegetable']];

            $block = Block::updateOrCreate(
                ['name' => $def['name']],
                [
                    'vegetable'          => $def['vegetable'],
                    'emoji'              => $cfg['emoji'],
                    'location'           => $cfg['location'],
                    'area_m2'            => rand(80, 150),
                    'moisture_threshold' => $cfg['moisture_threshold'],
                    'temp_min'           => $cfg['temp_min'],
                    'temp_max'           => $cfg['temp_max'],
                    'ph_min'             => $cfg['ph_min'],
                    'ph_max'             => $cfg['ph_max'],
                    'auto_water_enabled' => true,
                    'status'             => 'normal',
                    'description'        => $cfg['description'],
                ]
            );

            // ── Sensor readings: last 48 hours, every 10 minutes ──────────────
            $block->sensorReadings()->delete();
            $readings = [];
            $moisture = $cfg['moisture_base'];

            for ($min = 48 * 60; $min >= 0; $min -= 10) {
                $time = now()->subMinutes($min);
                $hour = $time->hour;
                $isDay = $hour >= 6 && $hour <= 18;

                // Simulate realistic moisture decay + variance per vegetable
                $moisture = max(20, $moisture - rand(0, 3) + rand(0, 1));

                // Temperature varies with time of day
                $tempBase = ($cfg['temp_min'] + $cfg['temp_max']) / 2;
                $temp = $tempBase + ($isDay ? rand(0, 40) / 10 : -rand(0, 20) / 10) + (rand(-10, 10) / 10);

                $readings[] = [
                    'block_id'       => $block->id,
                    'soil_moisture'  => round(max(15, min(95, $moisture)), 1),
                    'temperature'    => round(max(10, min(40, $temp)), 1),
                    'humidity'       => round(rand(50, 85) + ($isDay ? -5 : 5), 1),
                    'light_intensity'=> $isDay ? round(rand(2000, 8000) + 0.0) : round(rand(0, 200) + 0.0),
                    'ph_level'       => round($cfg['ph_min'] + (rand(0, 100) / 100) * ($cfg['ph_max'] - $cfg['ph_min']), 2),
                    'nitrogen'       => round(rand(30, 80) + 0.0, 1),
                    'phosphorus'     => round(rand(20, 60) + 0.0, 1),
                    'potassium'      => round(rand(40, 100) + 0.0, 1),
                    'is_raining'     => rand(0, 20) === 0,
                    'recorded_at'    => $time,
                    'created_at'     => $time,
                    'updated_at'     => $time,
                ];

                // Simulate watering when moisture gets low
                if ($moisture < $cfg['moisture_threshold'] - 5) {
                    $moisture = min(90, $moisture + rand(20, 35));

                    // Add a watering log entry
                    WateringLog::create([
                        'block_id'         => $block->id,
                        'user_id'          => rand(0, 1) ? null : $users[array_rand($users)]->id,
                        'trigger_type'     => rand(0, 2) === 0 ? 'manual' : 'auto',
                        'duration_seconds' => rand(20, 60),
                        'water_volume_liters' => rand(5, 20),
                        'moisture_before'  => $moisture - rand(20, 30),
                        'moisture_after'   => $moisture,
                        'started_at'       => $time,
                        'ended_at'         => $time->copy()->addSeconds(rand(20, 60)),
                        'created_at'       => $time,
                        'updated_at'       => $time,
                    ]);
                }
            }

            // Bulk insert for performance
            foreach (array_chunk($readings, 100) as $chunk) {
                SensorReading::insert($chunk);
            }

            // Update block status based on last moisture
            $lastMoisture = end($readings)['soil_moisture'];
            $status = 'normal';
            if ($lastMoisture < 25) $status = 'critical';
            elseif ($lastMoisture < $cfg['moisture_threshold']) $status = 'warning';
            $block->update(['status' => $status]);
        }

        $this->command->info('✅ SmartFarm seeded successfully!');
        $this->command->info('👤 Admin: admin@smartfarm.id / password');
        $this->command->info('👤 Operator: budi@smartfarm.id / password');
        $this->command->info('👤 Operator: siti@smartfarm.id / password');
    }
}
