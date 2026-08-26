<?php

namespace Tests\Unit\Services\Infrastructure\Geo;

use HiEvents\Services\Infrastructure\Geo\NoOpGeoProvider;
use Psr\Log\NullLogger;
use Tests\TestCase;

class NoOpGeoProviderTest extends TestCase
{
    public function test_all_lookups_are_empty_and_provider_reports_unavailable(): void
    {
        $provider = new NoOpGeoProvider(new NullLogger);

        $this->assertSame([], $provider->autocomplete('anything'));
        $this->assertNull($provider->getPlaceDetails('place_1'));
        $this->assertNull($provider->getCachedRawPlaceDetails('place_1'));
        $this->assertFalse($provider->isAvailable());
    }
}
