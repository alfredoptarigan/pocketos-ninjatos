<?php

namespace Tests\Unit\Game;

use App\Game\SearchOutcome;
use PHPUnit\Framework\TestCase;

class SearchOutcomeTest extends TestCase
{
    public function test_rolls_fall_into_the_rate_bands_in_order()
    {
        $rates = ['monster' => 1000, 'money' => 1000, 'item' => 6000];

        $this->assertSame('monster', SearchOutcome::pick($rates, 1));
        $this->assertSame('monster', SearchOutcome::pick($rates, 1000));
        $this->assertSame('money', SearchOutcome::pick($rates, 1001));
        $this->assertSame('item', SearchOutcome::pick($rates, 8000));
        $this->assertSame('nothing', SearchOutcome::pick($rates, 8001));
    }
}
