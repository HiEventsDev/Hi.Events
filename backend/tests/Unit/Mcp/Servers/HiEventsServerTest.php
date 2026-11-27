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

    public function test_registered_tools_have_boolean_safety_hints(): void
    {
        $tools = (new ReflectionClass(HiEventsServer::class))->getDefaultProperties()['tools'];

        foreach ($tools as $toolClass) {
            $annotations = (new ReflectionClass($toolClass))->newInstanceWithoutConstructor()->annotations();
            foreach (['readOnlyHint', 'destructiveHint', 'openWorldHint'] as $hint) {
                $this->assertArrayHasKey($hint, $annotations, $toolClass);
                $this->assertIsBool($annotations[$hint], $toolClass);
            }
            $this->assertFalse($annotations['readOnlyHint'] && $annotations['destructiveHint'], $toolClass);
        }
    }

    public function test_only_tools_that_reach_outside_the_account_are_open_world(): void
    {
        $tools = (new ReflectionClass(HiEventsServer::class))->getDefaultProperties()['tools'];

        $openWorld = collect($tools)
            ->filter(static fn (string $tool): bool => (new ReflectionClass($tool))->newInstanceWithoutConstructor()->annotations()['openWorldHint'])
            ->map(static fn (string $tool): string => class_basename($tool))
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'AddAttendeeTool',
            'AddLineupPerformerTool',
            'PublishEventTool',
            'ResendOrderConfirmationTool',
            'ResendTicketTool',
            'SetEventCoverImageTool',
            'UpdateTicketDesignTool',
        ], $openWorld);
    }

    public function test_the_tool_list_fits_on_one_page(): void
    {
        $defaults = (new ReflectionClass(HiEventsServer::class))->getDefaultProperties();

        $this->assertGreaterThanOrEqual(count($defaults['tools']), $defaults['defaultPaginationLength']);
        $this->assertGreaterThanOrEqual(count($defaults['tools']), $defaults['maxPaginationLength']);
    }
}
