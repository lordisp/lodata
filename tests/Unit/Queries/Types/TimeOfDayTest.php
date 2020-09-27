<?php

namespace Flat3\OData\Tests\Unit\Queries\Types;

use Flat3\OData\Tests\Request;

class TimeOfDayTest extends TypeTest
{
    public function test_filter_time_eq()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->path('/airports')
                ->filter('open_time eq 08:00:00')
                ->select('id,open_time')
        );
    }

    public function test_filter_time_gt()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->path('/airports')
                ->filter('open_time gt 08:30:00')
                ->select('id,open_time')
        );
    }

    public function test_filter_time_lt()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->path('/airports')
                ->filter('open_time lt 08:13:13')
                ->select('id,open_time')
        );
    }
}
