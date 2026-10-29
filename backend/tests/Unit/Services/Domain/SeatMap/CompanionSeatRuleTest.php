<?php

namespace Tests\Unit\Services\Domain\SeatMap;

use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Services\Domain\CompanionSeatRule;
use HiEvents\Enterprise\Seating\Services\Domain\SeatMapIndex;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanionSeatRuleTest extends TestCase
{
    public static function sharedCaseProvider(): array
    {
        $cases = json_decode(
            file_get_contents(__DIR__.'/../../../../Fixtures/seating/companion-rule-cases.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return collect($cases)->mapWithKeys(fn (array $case) => [$case['name'] => [$case['seats'], $case['excess']]])->all();
    }

    /**
     * @param  string[]  $seats
     */
    #[DataProvider('sharedCaseProvider')]
    public function test_matches_the_shared_fixture(array $seats, int $excess): void
    {
        $this->assertSame($excess, (new CompanionSeatRule)->excessCompanions(array_map(fn (string $kind) => [
            'accessible' => $kind === 'wheelchair',
            'companion' => $kind === 'companion',
        ], $seats)));
    }

    public function test_a_companion_seat_without_a_wheelchair_space_is_refused(): void
    {
        $this->expectException(SeatSelectionInvalidException::class);

        (new CompanionSeatRule)->assertAccompanied($this->theatreWithCompanionSeat(), ['e2.0.0', 'e2.0.1']);
    }

    public function test_a_companion_seat_beside_a_wheelchair_space_is_allowed(): void
    {
        (new CompanionSeatRule)->assertAccompanied($this->theatreWithCompanionSeat(), ['e2.0.0', 'e6.0.0']);

        $this->addToAssertionCount(1);
    }

    private function theatreWithCompanionSeat(): SeatMapIndex
    {
        $layout = json_decode(
            file_get_contents(base_path('tests/Fixtures/seating/theatre.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $layout['areas'][0]['elements'][1]['seats'][0]['comp'] = true;

        return SeatMapIndex::fromLayout($layout);
    }
}
