<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\WateringLog;
use App\Models\SensorReading;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary()
    {
        $blocks = Block::with('latestReading')->get();

        $totalBlocks = $blocks->count();
        $blocksNeedWater = $blocks->filter(fn($b) => $b->needsWater())->count();
        $blocksNormal = $blocks->filter(fn($b) => $b->status === 'normal')->count();
        $blocksWarning = $blocks->filter(fn($b) => in_array($b->status, ['warning', 'critical']))->count();

        $wateringToday = WateringLog::whereDate('started_at', today())->count();
        $wateringAutoToday = WateringLog::whereDate('started_at', today())
            ->where('trigger_type', 'auto')->count();

        $avgMoisture = SensorReading::whereIn('block_id', $blocks->pluck('id'))
            ->where('recorded_at', '>=', now()->subHour())
            ->avg('soil_moisture');

        $avgTemp = SensorReading::whereIn('block_id', $blocks->pluck('id'))
            ->where('recorded_at', '>=', now()->subHour())
            ->avg('temperature');

        // Moisture trend (last 12 hours, hourly avg per block)
        $moistureTrend = [];
        for ($h = 11; $h >= 0; $h--) {
            $time = now()->subHours($h);
            $row = ['time' => $time->format('H:i')];
            foreach ($blocks as $block) {
                $row[str_replace(' ', '_', $block->vegetable)] = round(
                    SensorReading::where('block_id', $block->id)
                        ->whereBetween('recorded_at', [$time->copy()->subMinutes(30), $time->copy()->addMinutes(30)])
                        ->avg('soil_moisture') ?? 0,
                    1
                );
            }
            $moistureTrend[] = $row;
        }

        return response()->json([
            'summary' => [
                'total_blocks'       => $totalBlocks,
                'blocks_need_water'  => $blocksNeedWater,
                'blocks_normal'      => $blocksNormal,
                'blocks_warning'     => $blocksWarning,
                'watering_today'     => $wateringToday,
                'watering_auto_today'=> $wateringAutoToday,
                'avg_moisture'       => round($avgMoisture ?? 0, 1),
                'avg_temperature'    => round($avgTemp ?? 0, 1),
            ],
            'blocks'          => $blocks->map(fn($b) => [
                'id'          => $b->id,
                'name'        => $b->name,
                'vegetable'   => $b->vegetable,
                'emoji'       => $b->emoji,
                'status'      => $b->status,
                'needs_water' => $b->needsWater(),
                'moisture'    => round($b->latestReading?->soil_moisture ?? 0, 1),
                'temperature' => round($b->latestReading?->temperature ?? 0, 1),
            ]),
            'moisture_trend' => $moistureTrend,
        ]);
    }
}
