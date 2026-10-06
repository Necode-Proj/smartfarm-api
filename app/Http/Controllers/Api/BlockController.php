<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\SensorReading;
use App\Models\WateringLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlockController extends Controller
{
    public function index()
    {
        $blocks = Block::with('latestReading')->get()->map(function ($block) {
            return $this->formatBlock($block);
        });

        return response()->json(['data' => $blocks]);
    }

    public function show($id)
    {
        $block = Block::with(['latestReading'])->findOrFail($id);

        // Last 24h readings for mini-chart
        $recentReadings = SensorReading::where('block_id', $id)
            ->where('recorded_at', '>=', now()->subHours(24))
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'soil_moisture', 'temperature', 'humidity', 'ph_level']);

        // Watering count today
        $wateringToday = WateringLog::where('block_id', $id)
            ->whereDate('started_at', today())
            ->count();

        return response()->json([
            'data' => $this->formatBlock($block),
            'recent_readings' => $recentReadings,
            'watering_today' => $wateringToday,
        ]);
    }

    public function readings(Request $request, $id)
    {
        $block = Block::findOrFail($id);

        $hours = $request->get('hours', 24);
        $limit = $request->get('limit', 100);

        $readings = SensorReading::where('block_id', $id)
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->orderBy('recorded_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'block_id' => $id,
            'block_name' => $block->name,
            'data' => $readings,
        ]);
    }

    // ─── Private Helpers ───────────────────────────────────────────────────────

    private function formatBlock(Block $block): array
    {
        $latest = $block->latestReading;

        return [
            'id'                   => $block->id,
            'name'                 => $block->name,
            'vegetable'            => $block->vegetable,
            'vegetable_display'    => $block->vegetable_display,
            'emoji'                => $block->emoji,
            'location'             => $block->location,
            'area_m2'              => $block->area_m2,
            'status'               => $block->status,
            'status_color'         => $block->status_color,
            'auto_water_enabled'   => $block->auto_water_enabled,
            'moisture_threshold'   => $block->moisture_threshold,
            'description'          => $block->description,
            'needs_water'          => $block->needsWater(),
            'latest_reading'       => $latest ? [
                'soil_moisture'  => round($latest->soil_moisture, 1),
                'temperature'    => round($latest->temperature, 1),
                'humidity'       => round($latest->humidity, 1),
                'light_intensity'=> round($latest->light_intensity, 0),
                'ph_level'       => round($latest->ph_level, 2),
                'is_raining'     => $latest->is_raining,
                'recorded_at'    => $latest->recorded_at,
            ] : null,
        ];
    }
}
