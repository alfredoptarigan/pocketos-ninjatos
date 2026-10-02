<?php

namespace Tests\Unit\Game;

use App\Game\Vitals;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VitalsTest extends TestCase
{
    public function test_health_recovers_over_time_up_to_the_maximum()
    {
        $since = Carbon::parse('2026-10-02 12:00:00');

        // 5% of 200 = 10 per minute.
        $this->assertSame(60, Vitals::recovered(current: 30, max: 200, since: $since, now: $since->copy()->addMinutes(3)));
        $this->assertSame(200, Vitals::recovered(current: 30, max: 200, since: $since, now: $since->copy()->addHour()));
    }

    public function test_nothing_recovers_without_a_timestamp()
    {
        $this->assertSame(30, Vitals::recovered(current: 30, max: 200, since: null, now: Carbon::now()));
    }
}
