<?php

namespace Tests\Unit\Services\Domain\Lineup;

use HiEvents\Services\Domain\Lineup\ScheduleDay;
use Tests\TestCase;

class ScheduleDayTest extends TestCase
{
    public function test_late_sets_stay_on_the_night_they_started(): void
    {
        $this->assertSame('2030-06-14', ScheduleDay::keyForUtc('2030-06-14 23:30:00', 'Europe/Dublin'));
        $this->assertSame('2030-06-14', ScheduleDay::keyForUtc('2030-06-15 03:30:00', 'Europe/Dublin'));
        $this->assertSame('2030-06-15', ScheduleDay::keyForUtc('2030-06-15 04:00:00', 'Europe/Dublin'));
    }

    public function test_clock_changes_use_the_local_wall_clock(): void
    {
        $this->assertSame('2030-10-26', ScheduleDay::keyForUtc('2030-10-27 04:30:00', 'Europe/Dublin'));
        $this->assertSame('2030-10-27', ScheduleDay::keyForUtc('2030-10-27 05:00:00', 'Europe/Dublin'));
        $this->assertSame('2030-03-31', ScheduleDay::keyForUtc('2030-03-31 04:30:00', 'Europe/Dublin'));
        $this->assertSame('2030-03-30', ScheduleDay::keyForUtc('2030-03-31 02:30:00', 'Europe/Dublin'));
    }
}
