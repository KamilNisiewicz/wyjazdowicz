<?php

namespace Tests\Unit;

use App\Services\DistanceCalculator;
use Tests\TestCase;

class DistanceCalculatorTest extends TestCase
{
    public function test_kilometers_between_known_city_pair_warszawa_krakow(): void
    {
        $km = (new DistanceCalculator)->kilometersBetween(52.2297, 21.0122, 50.0647, 19.9450);

        // Rzeczywisty dystans Warszawa-Kraków w linii prostej to ok. 252 km.
        $this->assertGreaterThanOrEqual(240, $km);
        $this->assertLessThanOrEqual(265, $km);
    }

    public function test_kilometers_between_identical_coordinates_is_zero(): void
    {
        $km = (new DistanceCalculator)->kilometersBetween(52.2297, 21.0122, 52.2297, 21.0122);

        $this->assertSame(0, $km);
    }
}
