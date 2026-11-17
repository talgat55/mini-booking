<?php
namespace Database\Seeders;

use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $today = CarbonImmutable::today(config('app.timezone'));
        for ($day = 0; $day < 7; $day++) {
            for ($hour = 9; $hour < 18; $hour++) {
                $start = $today->addDays($day)->setTime($hour, 0);
                Slot::firstOrCreate(
                    ['starts_at' => $start->timestamp],
                    ['ends_at' => $start->addHour()->timestamp],
                );
            }
        }
    }
}
