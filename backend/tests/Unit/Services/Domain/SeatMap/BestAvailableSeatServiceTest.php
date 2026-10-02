<?php

namespace Tests\Unit\Services\Domain\SeatMap;

use HiEvents\Enterprise\Seating\Services\Domain\BestAvailableSeatService;
use HiEvents\Enterprise\Seating\Services\Domain\CompanionSeatRule;
use HiEvents\Enterprise\Seating\Services\Domain\OrphanSeatRule;
use HiEvents\Enterprise\Seating\Services\Domain\SeatMapIndex;
use Tests\TestCase;

class BestAvailableSeatServiceTest extends TestCase
{
    private SeatMapIndex $theatre;

    private BestAvailableSeatService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->theatre = SeatMapIndex::fromLayout($this->fixture('theatre'));
        $this->service = new BestAvailableSeatService(new OrphanSeatRule, new CompanionSeatRule);
    }

    public function test_picks_adjacent_front_row_centre_seats_in_the_requested_band(): void
    {
        $seats = $this->service->find($this->theatre, ['b_premium'], [], 2);

        $this->assertCount(2, $seats);
        $this->assertSame(['Stalls · A-10', 'Stalls · A-11'], array_map(fn ($uid) => $this->theatre->labelOf($uid), $seats));
    }

    public function test_never_strands_a_single_seat_when_an_alternative_exists(): void
    {
        $unavailable = ['e2.0.7', 'e2.0.8', 'e2.0.9', 'e2.0.10'];

        $seats = $this->service->find($this->theatre, ['b_premium'], $unavailable, 2);

        $this->assertSame([], (new OrphanSeatRule)->findOrphans($this->theatre->segments(), $unavailable, $seats));
        $this->assertCount(2, $seats);
    }

    public function test_skips_unavailable_seats_and_other_bands(): void
    {
        $seats = $this->service->find($this->theatre, ['b_value'], ['e11.0.11', 'e11.0.12'], 3);

        $this->assertCount(3, $seats);
        foreach ($seats as $uid) {
            $this->assertSame('b_value', $this->theatre->bandOf($uid));
            $this->assertNotContains($uid, ['e11.0.11', 'e11.0.12']);
        }
    }

    public function test_falls_back_to_nearby_seats_when_no_row_can_seat_the_party_together(): void
    {
        $banquet = SeatMapIndex::fromLayout($this->fixture('banquet'));

        $seats = $this->service->find($banquet, ['b_premium'], [], 4);

        $this->assertCount(4, $seats);
        $this->assertCount(4, array_unique($seats));
    }

    public function test_accessible_seats_are_only_offered_when_asked_for(): void
    {
        $standard = $this->service->find($this->theatre, ['b_standard'], [], 2);
        $accessible = $this->service->find($this->theatre, ['b_standard'], [], 2, accessibleOnly: true);

        foreach ($standard as $uid) {
            $this->assertFalse($this->theatre->seats()[$uid]['accessible']);
        }
        foreach ($accessible as $uid) {
            $this->assertTrue($this->theatre->seats()[$uid]['accessible']);
        }
        $this->assertCount(2, $accessible);
    }

    public function test_returns_nothing_when_too_few_seats_are_free(): void
    {
        $this->assertSame([], $this->service->find($this->theatre, ['b_standard'], [], 7, accessibleOnly: true));
    }

    public function test_standard_requests_never_return_companion_seats(): void
    {
        $layout = $this->fixture('theatre');
        $layout['areas'][0]['elements'][1]['seats'][9]['comp'] = true;
        $layout['areas'][0]['elements'][1]['seats'][10]['comp'] = true;
        $index = SeatMapIndex::fromLayout($layout);

        $seats = $this->service->find($index, ['b_premium'], [], 2);

        $this->assertCount(2, $seats);
        $this->assertSame([], array_intersect($seats, ['e2.0.9', 'e2.0.10']));
    }

    public function test_accessible_requests_pair_companion_seats_with_wheelchair_spaces(): void
    {
        $index = $this->theatreWithOneWheelchairSpaceAndCompanions();

        $this->assertSame(['e6.0.0', 'e6.0.1'], $this->service->find($index, ['b_standard'], [], 2, accessibleOnly: true));
    }

    public function test_accessible_requests_never_return_more_companions_than_wheelchair_spaces(): void
    {
        $index = $this->theatreWithOneWheelchairSpaceAndCompanions();

        $this->assertSame([], $this->service->find($index, ['b_standard'], [], 3, accessibleOnly: true));
        $this->assertSame([], $this->service->find($index, ['b_standard'], ['e6.0.0'], 1, accessibleOnly: true));
    }

    private function theatreWithOneWheelchairSpaceAndCompanions(): SeatMapIndex
    {
        $layout = $this->fixture('theatre');
        foreach ($layout['areas'][0]['elements'][5]['seats'] as $position => &$seat) {
            $seat['acc'] = $position === 0;
            $seat['comp'] = $position !== 0;
        }

        return SeatMapIndex::fromLayout($layout);
    }

    private function fixture(string $template): array
    {
        return json_decode(
            file_get_contents(base_path("tests/Fixtures/seating/$template.json")),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
