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
    private array $veggieConfig = [
        'wortel' => [
            'emoji'               => '🥕',
            'moisture_threshold'  => 40,
            'moisture_base'       => 62,
            'temp_base'           => 21,
            'temp_min'            => 18,
            'temp_max'            => 24,
            'ph_min'              => 6.0,
            'ph_max'              => 6.8,
            'description'         => 'Blok tanaman wortel organik varietas Chantenay. Cocok untuk tanah berpasir dengan drainase baik.',
            'location'            => 'Lahan A - Barat',
            'area_m2'             => 120,
        ],
        'tomat' => [
            'emoji'               => '🍅',
            'moisture_threshold'  => 50,
            'moisture_base'       => 68,
            'temp_base'           => 24,
            'temp_min'            => 20,
            'temp_max'            => 27,
            'ph_min'              => 6.0,
            'ph_max'              => 6.8,
            'description'         => 'Blok tanaman tomat cherry merah varietas Cantaloupe. Tahan penyakit dan produktivitas tinggi.',
            'location'            => 'Lahan B - Tengah',
            'area_m2'             => 100,
        ],
        'bayam' => [
            'emoji'               => '🌿',
            'moisture_threshold'  => 60,
            'moisture_base'       => 72,
            'temp_base'           => 18,
            'temp_min'            => 15,
            'temp_max'            => 22,
            'ph_min'              => 6.5,
            'ph_max'              => 7.0,
            'description'         => 'Blok tanaman bayam hijau varietas Malabar. Tumbuh cepat, kaya zat besi dan nutrisi.',
            'location'            => 'Lahan C - Timur',
            'area_m2'             => 80,
        ],
    ];

    public function run(): void
    {
        // ── 1. Users ──────────────────────────────────────────────────────────
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

        // ── 2. Blocks & Sensor Readings ───────────────────────────────────────
        $blockDefs = [
            ['name' => 'Blok A - Wortel', 'vegetable' => 'wortel'],
            ['name' => 'Blok B - Tomat',  'vegetable' => 'tomat'],
            ['name' => 'Blok C - Bayam',  'vegetable' => 'bayam'],
        ];

        // Clean out existing watering logs first so everything stays consistent
        WateringLog::truncate();

        $createdBlocks = [];
        $now = now();

        foreach ($blockDefs as $def) {
            $cfg = $this->veggieConfig[$def['vegetable']];

            $block = Block::updateOrCreate(
                ['name' => $def['name']],
                [
                    'vegetable'           => $def['vegetable'],
                    'emoji'               => $cfg['emoji'],
                    'location'            => $cfg['location'],
                    'area_m2'             => $cfg['area_m2'],
                    'moisture_threshold'  => $cfg['moisture_threshold'],
                    'temp_min'            => $cfg['temp_min'],
                    'temp_max'            => $cfg['temp_max'],
                    'ph_min'              => $cfg['ph_min'],
                    'ph_max'              => $cfg['ph_max'],
                    'auto_water_enabled'  => true,
                    'status'              => 'normal',
                    'description'         => $cfg['description'],
                ]
            );

            // Clear old sensor readings
            $block->sensorReadings()->delete();

            // ── Sensor readings: last 72 hours, every 5 minutes ──────────────
            $readings  = [];
            $moisture  = $cfg['moisture_base'];
            $totalMins = 72 * 60;

            for ($min = $totalMins; $min >= 0; $min -= 5) {
                $time    = $now->copy()->subMinutes($min);
                $hour    = (int) $time->format('H');
                $isDay   = $hour >= 6 && $hour <= 18;

                // Natural moisture decay
                $decayRate = $isDay
                    ? ($cfg['moisture_threshold'] / 100) * 1.8
                    : ($cfg['moisture_threshold'] / 100) * 0.4;
                $moisture  = max(15, $moisture - ($decayRate * (rand(70, 130) / 100)));

                // Temperature: rises in daytime, drops at night
                $tempRange = ($cfg['temp_max'] - $cfg['temp_min']) / 2;
                $tempCycle  = $isDay
                    ? $cfg['temp_base'] + ($tempRange * sin(($hour - 6) / 12 * M_PI)) + (rand(-5, 5) / 10)
                    : $cfg['temp_base'] - ($tempRange * 0.5) + (rand(-5, 5) / 10);

                // Humidity: higher at night/morning
                $humidity = $isDay
                    ? rand(55, 72) + (rand(-5, 5) / 10)
                    : rand(68, 88) + (rand(-5, 5) / 10);

                $lightIntensity = $isDay
                    ? max(0, round(8000 * sin(($hour - 6) / 12 * M_PI) * (rand(70, 100) / 100)))
                    : rand(0, 80);

                $readings[] = [
                    'block_id'        => $block->id,
                    'soil_moisture'   => round(max(15, min(95, $moisture)), 1),
                    'temperature'     => round(max(10, min(40, $tempCycle)), 1),
                    'humidity'        => round(max(30, min(98, $humidity)), 1),
                    'light_intensity' => $lightIntensity,
                    'ph_level'        => round(
                        $cfg['ph_min'] + (rand(0, 100) / 100) * ($cfg['ph_max'] - $cfg['ph_min']),
                        2
                    ),
                    'nitrogen'        => round(rand(35, 85), 1),
                    'phosphorus'      => round(rand(20, 65), 1),
                    'potassium'       => round(rand(45, 110), 1),
                    'is_raining'      => rand(0, 30) === 0, // ~3% chance
                    'recorded_at'     => $time->toDateTimeString(),
                    'created_at'      => $time->toDateTimeString(),
                    'updated_at'      => $time->toDateTimeString(),
                ];

                // Auto-water when moisture drops below threshold
                if ($moisture < $cfg['moisture_threshold']) {
                    $boost    = rand(22, 35);
                    $moistureBefore = round($moisture, 1);
                    $moisture = min(92, $moisture + $boost);
                    $moistureAfter = round($moisture, 1);

                    WateringLog::create([
                        'block_id'             => $block->id,
                        'user_id'              => null, // auto = system
                        'trigger_type'         => 'auto',
                        'duration_seconds'     => rand(25, 55),
                        'water_volume_liters'  => round(rand(80, 200) / 10, 1),
                        'moisture_before'      => $moistureBefore,
                        'moisture_after'       => $moistureAfter,
                        'note'                 => 'Penyiraman otomatis: kelembaban tanah turun di bawah threshold ' . $cfg['moisture_threshold'] . '%.',
                        'started_at'           => $time->toDateTimeString(),
                        'ended_at'             => $time->copy()->addSeconds(rand(25, 55))->toDateTimeString(),
                        'created_at'           => $time->toDateTimeString(),
                        'updated_at'           => $time->toDateTimeString(),
                    ]);
                }
            }

            // Ensure the latest reading is fresh and in an optimal healthy range
            $latestReadingTime = $now->copy();
            $optimalMoisture = match ($def['vegetable']) {
                'wortel' => rand(48, 56) + (rand(0, 9) / 10),
                'tomat'  => rand(58, 66) + (rand(0, 9) / 10),
                'bayam'  => rand(68, 76) + (rand(0, 9) / 10),
            };
            $optimalTemp = match ($def['vegetable']) {
                'wortel' => 21.5 + (rand(-10, 10) / 10),
                'tomat'  => 24.0 + (rand(-10, 10) / 10),
                'bayam'  => 19.5 + (rand(-10, 10) / 10),
            };

            $readings[count($readings) - 1] = [
                'block_id'        => $block->id,
                'soil_moisture'   => round($optimalMoisture, 1),
                'temperature'     => round($optimalTemp, 1),
                'humidity'        => round(rand(62, 75), 1),
                'light_intensity' => rand(4500, 7500),
                'ph_level'        => round(($cfg['ph_min'] + $cfg['ph_max']) / 2, 2),
                'nitrogen'        => round(rand(50, 70), 1),
                'phosphorus'      => round(rand(35, 50), 1),
                'potassium'       => round(rand(65, 85), 1),
                'is_raining'      => false,
                'recorded_at'     => $latestReadingTime->toDateTimeString(),
                'created_at'      => $latestReadingTime->toDateTimeString(),
                'updated_at'      => $latestReadingTime->toDateTimeString(),
            ];

            // Bulk insert sensor readings
            foreach (array_chunk($readings, 200) as $chunk) {
                SensorReading::insert($chunk);
            }

            // Status is normal
            $block->update(['status' => 'normal']);
            $createdBlocks[] = $block;
        }

        // ── 3. Manual & Scheduled Watering Logs (including TODAY) ────────────
        // Create manual logs for previous 6 days
        foreach ($createdBlocks as $block) {
            for ($day = 6; $day >= 1; $day--) {
                $numManualToday = rand(1, 3);
                for ($m = 0; $m < $numManualToday; $m++) {
                    $hour       = rand(7, 17);
                    $minute     = rand(0, 59);
                    $watered_at = $now->copy()->subDays($day)->setHour($hour)->setMinute($minute)->setSecond(0);

                    $moistureBefore = rand(32, 45);
                    $boost          = rand(20, 32);
                    $moistureAfter  = min(90, $moistureBefore + $boost);
                    $user           = $users[array_rand($users)];
                    $duration       = rand(25, 50);

                    WateringLog::create([
                        'block_id'             => $block->id,
                        'user_id'              => $user->id,
                        'trigger_type'         => 'manual',
                        'duration_seconds'     => $duration,
                        'water_volume_liters'  => round(rand(60, 160) / 10, 1),
                        'moisture_before'      => $moistureBefore,
                        'moisture_after'       => $moistureAfter,
                        'note'                 => $this->randomNote($block->vegetable, $moistureBefore),
                        'started_at'           => $watered_at->toDateTimeString(),
                        'ended_at'             => $watered_at->copy()->addSeconds($duration)->toDateTimeString(),
                        'created_at'           => $watered_at->toDateTimeString(),
                        'updated_at'           => $watered_at->toDateTimeString(),
                    ]);
                }
            }
        }

        // Add guaranteed watering logs for TODAY (both auto and manual)
        // This ensures Dashboard "Penyiraman Hari Ini" has realistic data (e.g., 8-15 penyiraman)
        $todaySchedules = [
            ['block_idx' => 0, 'hour' => 6,  'min' => 15, 'type' => 'auto',   'user' => null,   'sec' => 35, 'before' => 38.5, 'after' => 68.0, 'note' => 'Penyiraman otomatis pagi: kelembaban Wortel turun ke 38.5%'],
            ['block_idx' => 1, 'hour' => 7,  'min' => 10, 'type' => 'auto',   'user' => null,   'sec' => 40, 'before' => 48.0, 'after' => 75.5, 'note' => 'Penyiraman otomatis pagi: kelembaban Tomat turun ke 48.0%'],
            ['block_idx' => 2, 'hour' => 7,  'min' => 45, 'type' => 'manual', 'user' => $op1,   'sec' => 45, 'before' => 58.0, 'after' => 82.0, 'note' => 'Penyiraman manual rutin pagi hari untuk menjaga kesegaran Bayam.'],
            ['block_idx' => 0, 'hour' => 9,  'min' => 30, 'type' => 'manual', 'user' => $op2,   'sec' => 30, 'before' => 45.0, 'after' => 70.0, 'note' => 'Pengecekan kelembaban tanah blok wortel, dilakukan penyiraman tambahan.'],
            ['block_idx' => 2, 'hour' => 10, 'min' => 20, 'type' => 'auto',   'user' => null,   'sec' => 50, 'before' => 59.2, 'after' => 84.0, 'note' => 'Penyiraman otomatis: kelembaban tanah Bayam turun di bawah threshold 60%.'],
            ['block_idx' => 1, 'hour' => 11, 'min' => 05, 'type' => 'manual', 'user' => $admin, 'sec' => 35, 'before' => 52.0, 'after' => 76.0, 'note' => 'Siram manual blok tomat menjelang siang hari panas terik.'],
        ];

        foreach ($todaySchedules as $sched) {
            $time = $now->copy()->setHour($sched['hour'])->setMinute($sched['min'])->setSecond(0);
            if ($time->isFuture()) {
                $time = $now->copy()->subHours(rand(1, 4))->subMinutes(rand(0, 30));
            }
            $targetBlock = $createdBlocks[$sched['block_idx']];

            WateringLog::create([
                'block_id'             => $targetBlock->id,
                'user_id'              => $sched['user']?->id,
                'trigger_type'         => $sched['type'],
                'duration_seconds'     => $sched['sec'],
                'water_volume_liters'  => round(rand(80, 180) / 10, 1),
                'moisture_before'      => $sched['before'],
                'moisture_after'       => $sched['after'],
                'note'                 => $sched['note'],
                'started_at'           => $time->toDateTimeString(),
                'ended_at'             => $time->copy()->addSeconds($sched['sec'])->toDateTimeString(),
                'created_at'           => $time->toDateTimeString(),
                'updated_at'           => $time->toDateTimeString(),
            ]);
        }

        $this->command->info('');
        $this->command->info('✅  SmartFarm database seeded successfully with realistic data!');
        $this->command->info('📊  Blocks          : 3 (Wortel, Tomat, Bayam)');
        $this->command->info('📈  Sensor readings : 72 jam × 3 blok (setiap 5 menit sampai ' . $now->toDateTimeString() . ')');
        $this->command->info('💧  Watering logs   : ' . WateringLog::count() . ' total logs (termasuk hari ini)');
        $this->command->info('');
        $this->command->info('👤  admin@smartfarm.id    / password  (admin)');
        $this->command->info('👤  budi@smartfarm.id     / password  (operator)');
        $this->command->info('👤  siti@smartfarm.id     / password  (operator)');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function randomNote(string $vegetable, float $moisture): string
    {
        $notes = [
            'wortel' => [
                'Tanah mulai kering, disiram rutin untuk menjaga umbi wortel.',
                'Penyiraman rutin pagi hari untuk menjaga kelembaban optimal.',
                'Kelembaban rendah (' . $moisture . '%), disiram manual oleh petugas lapangan.',
                'Pengecekan mingguan blok wortel — siram preventif.',
                'Suhu naik hari ini, siram ekstra agar umbi tetap lembab.',
            ],
            'tomat'  => [
                'Tomat membutuhkan air ekstra saat berbuah. Siram sekarang.',
                'Kelembaban tanah ' . $moisture . '% — disiram agar tomat tidak layu.',
                'Penyiraman sore hari untuk menjaga kestabilan suhu tanah.',
                'Pengecekan blok tomat, tanah terasa kering saat diraba.',
                'Daun tomat mulai sedikit layu — segera disiram optimal.',
            ],
            'bayam'  => [
                'Bayam sangat sensitif terhadap kekeringan. Disiram segera.',
                'Kelembaban ' . $moisture . '% — siram agar bayam tumbuh optimal dan segar.',
                'Penyiraman pagi, bayam butuh kelembaban tinggi untuk tumbuh cepat.',
                'Pemantauan blok bayam — tanah mulai mengering di permukaan bedengan.',
                'Bayam muda baru ditanam, perlu penyiraman berkala yang merata.',
            ],
        ];

        $list = $notes[$vegetable] ?? $notes['wortel'];
        return $list[array_rand($list)];
    }
}
