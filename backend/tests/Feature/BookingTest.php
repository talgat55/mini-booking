<?php
namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Database\Seeders\ScheduleSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:00:00', config('app.timezone')));
    }

    private function futureSlot(): Slot
    {
        return Slot::create(['starts_at' => now()->addDay()->setTime(9, 0)->timestamp,
            'ends_at' => now()->addDay()->setTime(10, 0)->timestamp]);
    }

    private function payload(Slot $slot): array
    {
        return ['slot_id' => $slot->id, 'customer_name' => 'Анна Иванова', 'customer_email' => 'anna@example.com'];
    }

    public function test_future_slot_can_be_booked(): void
    {
        $slot = $this->futureSlot();
        $response = $this->postJson('/api/bookings', $this->payload($slot));
        $response->assertCreated()->assertJsonStructure(['id']);
        $this->assertDatabaseHas('bookings', ['id' => $response->json('id'), 'slot_id' => $slot->id,
            'customer_name' => 'Анна Иванова', 'customer_email' => 'anna@example.com']);
    }

    public function test_second_booking_returns_conflict_and_creates_no_duplicate(): void
    {
        $slot = $this->futureSlot();
        $this->postJson('/api/bookings', $this->payload($slot))->assertCreated();
        $this->postJson('/api/bookings', $this->payload($slot))->assertConflict()->assertJsonStructure(['message']);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_cancellation_releases_future_slot_and_is_idempotent(): void
    {
        $slot = $this->futureSlot();
        $id = $this->postJson('/api/bookings', $this->payload($slot))->json('id');
        $this->deleteJson('/api/bookings/'.$id)->assertNoContent();
        $this->deleteJson('/api/bookings/'.$id)->assertNoContent();
        $this->getJson('/api/slots')->assertJsonPath('data.0.status', 'available')->assertJsonPath('data.0.booking_id', null);
        $this->postJson('/api/bookings', $this->payload($slot))->assertCreated();
    }

    public function test_slot_starting_exactly_now_is_past_and_cannot_be_booked(): void
    {
        $slot = Slot::create(['starts_at' => now()->timestamp, 'ends_at' => now()->addHour()->timestamp]);
        $this->postJson('/api/bookings', $this->payload($slot))->assertConflict();
        $this->getJson('/api/slots')->assertJsonPath('data.0.status', 'past');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_invalid_fields_and_nonexistent_slot_return_field_errors(): void
    {
        $this->postJson('/api/bookings', ['slot_id' => 99999, 'customer_name' => ' ', 'customer_email' => 'bad'])
            ->assertUnprocessable()->assertJsonValidationErrors(['slot_id', 'customer_name', 'customer_email']);
    }

    public function test_slot_list_is_ordered_has_timezone_and_excludes_personal_data(): void
    {
        $slot = $this->futureSlot();
        $this->postJson('/api/bookings', $this->payload($slot))->assertCreated();
        Slot::create(['starts_at' => now()->timestamp - 3600, 'ends_at' => now()->timestamp]);
        $response = $this->getJson('/api/slots')->assertOk()
            ->assertJsonPath('timezone', config('app.timezone'))
            ->assertJsonPath('data.0.status', 'past')
            ->assertJsonPath('data.1.status', 'booked');
        $this->assertStringContainsString('+06:00', $response->json('data.1.starts_at'));
        $this->assertArrayNotHasKey('customer_name', $response->json('data.1'));
        $this->assertArrayNotHasKey('customer_email', $response->json('data.1'));
        $response->assertDontSee('anna@example.com')->assertDontSee('Анна Иванова');
    }

    public function test_seeder_is_idempotent_preserves_bookings_and_extends_schedule(): void
    {
        $this->seed(ScheduleSeeder::class);
        $this->assertDatabaseCount('slots', 63);
        $slot = Slot::where('starts_at', '>', now()->timestamp)->firstOrFail();
        $id = $this->postJson('/api/bookings', $this->payload($slot))->json('id');
        $this->seed(ScheduleSeeder::class);
        $this->assertDatabaseCount('slots', 63);
        $this->assertDatabaseHas('bookings', ['id' => $id, 'slot_id' => $slot->id]);
        $this->travel(1)->days();
        $this->seed(ScheduleSeeder::class);
        $this->assertDatabaseCount('slots', 72);
        $this->assertDatabaseHas('bookings', ['id' => $id]);
    }

    public function test_database_rejects_a_second_active_booking_even_without_controller(): void
    {
        $slot = $this->futureSlot();
        Booking::create($this->payload($slot));
        $this->expectException(UniqueConstraintViolationException::class);
        Booking::create($this->payload($slot));
    }
}
