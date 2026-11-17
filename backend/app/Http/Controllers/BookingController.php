<?php
namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Slot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'email', 'max:254'],
        ]);
        try {
            $booking = DB::transaction(function () use ($data) {
                // A write acquires SQLite's writer lock (and a row lock on MySQL/Postgres).
                // Check time only AFTER acquiring the lock, including after a retry.
                Slot::whereKey($data['slot_id'])->update(['id' => DB::raw('id')]);
                $slot = Slot::findOrFail($data['slot_id']);
                abort_if($slot->starts_at <= now()->timestamp, 409, 'Этот слот уже начался. Выберите будущее время.');
                abort_if($slot->booking()->exists(), 409, 'Этот слот уже забронирован. Выберите другое время.');
                return Booking::create($data);
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            return response()->json(['message' => 'Этот слот уже забронирован. Выберите другое время.'], 409);
        }
        return response()->json(['id' => $booking->id], 201);
    }

    public function destroy(int $id): Response
    {
        Booking::whereKey($id)->delete();
        return response()->noContent();
    }
}
