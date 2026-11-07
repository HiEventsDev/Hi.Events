<?php

namespace Tests\Unit\Mcp\Servers;

use HiEvents\Mcp\Servers\HiEventsServer;
use ReflectionClass;
use Tests\TestCase;

class HiEventsServerTest extends TestCase
{
    public function test_every_registered_tool_is_exercised_by_a_unit_test(): void
    {
        $tools = (new ReflectionClass(HiEventsServer::class))->getDefaultProperties()['tools'];
        $testSource = collect(glob(base_path('tests/Unit/Mcp/Tools/*Test.php')))
            ->map(static fn (string $path): string => file_get_contents($path))
            ->implode("\n");

        $untested = collect($tools)
            ->map(static fn (string $tool): string => class_basename($tool))
            ->reject(static fn (string $tool): bool => str_contains($testSource, "new $tool(") || str_contains($testSource, "$tool::class"))
            ->values()
            ->all();

        $this->assertSame([], $untested, 'Add a unit test under tests/Unit/Mcp/Tools for: '.implode(', ', $untested));
    }

    public function test_the_tool_list_fits_on_one_page(): void
    {
        $defaults = (new ReflectionClass(HiEventsServer::class))->getDefaultProperties();

        $this->assertGreaterThanOrEqual(count($defaults['tools']), $defaults['defaultPaginationLength']);
        $this->assertGreaterThanOrEqual(count($defaults['tools']), $defaults['maxPaginationLength']);
    }
}
