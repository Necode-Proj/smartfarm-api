<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\WateringLog;
use Illuminate\Http\Request;
use Carbon\Carbon;

class WateringController extends Controller
{
    /**
     * Trigger manual watering for a block.
     */
    public function water(Request $request, $blockId)
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $block = Block::with('latestReading')->findOrFail($blockId);

        $moistureBefore = $block->latestReading?->soil_moisture;

        $log = WateringLog::create([
            'block_id'        => $block->id,
            'user_id'         => $request->user()->id,
            'trigger_type'    => 'manual',
            'duration_seconds'=> 30,
            'moisture_before' => $moistureBefore,
            'note'            => $request->note,
            'started_at'      => now(),
            'ended_at'        => now()->addSeconds(30),
        ]);

        // Update block status to watering
        $block->update(['status' => 'watering']);

        // Simulate moisture increase after watering
        if ($block->latestReading) {
            $newMoisture = min(90, ($moistureBefore ?? 40) + rand(15, 25));
            $block->sensorReadings()->create(array_merge(
                $block->latestReading->only([
                    'temperature', 'humidity', 'light_intensity', 'ph_level',
                    'nitrogen', 'phosphorus', 'potassium',
                ]),
                [
                    'soil_moisture' => $newMoisture,
                    'recorded_at' => now()->addSeconds(35),
                ]
            ));
            $log->update(['moisture_after' => $newMoisture]);
        }

        // Restore to normal status after watering
        $block->update(['status' => 'normal']);

        return response()->json([
            'message' => 'Penyiraman berhasil dilakukan!',
            'log'     => [
                'id'             => $log->id,
                'trigger_type'   => $log->trigger_type,
                'moisture_before'=> $log->moisture_before,
                'moisture_after' => $log->fresh()->moisture_after,
                'started_at'     => $log->started_at,
            ],
        ]);
    }

    /**
     * Get watering logs with filters.
     */
    public function logs(Request $request)
    {
        $query = WateringLog::with(['block:id,name,vegetable,emoji', 'user:id,name'])
            ->orderByDesc('started_at');

        if ($request->block_id) {
            $query->where('block_id', $request->block_id);
        }

        if ($request->trigger_type) {
            $query->where('trigger_type', $request->trigger_type);
        }

        if ($request->date_from) {
            $query->whereDate('started_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('started_at', '<=', $request->date_to);
        }

        $logs = $query->paginate($request->get('per_page', 20));

        return response()->json($logs);
    }
}
