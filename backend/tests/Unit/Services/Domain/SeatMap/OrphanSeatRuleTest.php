<?php

namespace Tests\Unit\Services\Domain\SeatMap;

use HiEvents\Enterprise\Seating\Services\Domain\OrphanSeatRule;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrphanSeatRuleTest extends TestCase
{
    public static function sharedCases(): array
    {
        $cases = json_decode(
            file_get_contents(__DIR__.'/../../../../Fixtures/seating/orphan-rule-cases.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return array_combine(array_column($cases, 'name'), array_map(static fn (array $case) => [$case], $cases));
    }

    #[DataProvider('sharedCases')]
    public function test_shared_fixture_case(array $case): void
    {
        $this->assertSame(
            $case['orphans'],
            (new OrphanSeatRule)->findOrphans([$case['segment']], $case['taken'], $case['selected']),
        );
    }

    public function test_orphans_are_collected_across_segments(): void
    {
        $orphans = (new OrphanSeatRule)->findOrphans(
            [['a1', 'a2', 'a3', 'a4', 'a5'], ['b1', 'b2', 'b3', 'b4', 'b5'], ['c1', 'c2', 'c3']],
            ['c2'],
            ['a2', 'a3', 'a4', 'b1', 'b2', 'b3'],
        );

        $this->assertSame(['a1', 'a5'], $orphans);
    }
}
