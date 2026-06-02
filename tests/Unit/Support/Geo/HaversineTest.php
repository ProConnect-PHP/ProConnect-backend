<?php

namespace Tests\Unit\Support\Geo;

use App\Support\Geo\Haversine;
use PHPUnit\Framework\TestCase;

class HaversineTest extends TestCase
{
    public function test_calculates_distance_between_montevideo_and_buenos_aires(): void
    {
        $distance = Haversine::distanceBetween(
            -34.9011,
            -56.1645,
            -34.6037,
            -58.3816
        );

        $this->assertGreaterThan(190, $distance);
        $this->assertLessThan(230, $distance);
    }

    public function test_sql_expression_uses_services_coordinates(): void
    {
        $expression = Haversine::distanceExpression();

        $this->assertStringContainsString('services.latitude', $expression);
        $this->assertStringContainsString('services.longitude', $expression);
    }
}
