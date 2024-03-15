<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;
use DTApi\Helpers\TeHelper;

class TeHelperTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function testWillExpireAtMethod()
    {
        $dueTime = Carbon::now();

        // First assertion
        $createdAt = $dueTime->addHours(89);
        $expiredAt = TeHelper::willExpireAt($dueTime, $createdAt);
        $this->assertEquals($dueTime->format('Y-m-d H:i:s'), $expiredAt);

        // 2nd assertion
        $createdAt = $dueTime->addHours(20);
        $expiredAt = TeHelper::willExpireAt($dueTime, $createdAt);
        $this->assertEquals($createdAt->addMinutes(90)->format('Y-m-d H:i:s'), $expiredAt);

        // 3rd assertion
        $createdAt = $dueTime->addHours(50);
        $expiredAt = TeHelper::willExpireAt($dueTime, $createdAt);
        $this->assertEquals($createdAt->addHours(16)->format('Y-m-d H:i:s'), $expiredAt);

        // Last assertion
        $createdAt = $dueTime->addHours(91);
        $expiredAt = TeHelper::willExpireAt($dueTime, $createdAt);
        $this->assertEquals($dueTime->subHours(48)->format('Y-m-d H:i:s'), $expiredAt);
    }
}