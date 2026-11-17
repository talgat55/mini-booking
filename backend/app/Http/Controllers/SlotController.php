<?php
namespace App\Http\Controllers;

use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class SlotController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $now = now()->timestamp;
        $timezone = config('app.timezone');
        $slots = Slot::with('booking:id,slot_id')->orderBy('starts_at')->get();
        return response()->json([
            'timezone' => $timezone,
            'server_time' => now()->toIso8601String(),
            'data' => $slots->map(fn (Slot $slot) => [
                'id' => $slot->id,
                'starts_at' => CarbonImmutable::createFromTimestamp($slot->starts_at, $timezone)->toIso8601String(),
                'ends_at' => CarbonImmutable::createFromTimestamp($slot->ends_at, $timezone)->toIso8601String(),
                'status' => $slot->starts_at <= $now ? 'past' : ($slot->booking ? 'booked' : 'available'),
                'booking_id' => $slot->booking?->id,
            ]),
        ]);
    }
}
