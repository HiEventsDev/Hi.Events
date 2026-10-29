<?php

namespace Tests\Unit\Services\Domain\SeatMap;

use HiEvents\Enterprise\Seating\Services\Domain\SeatMapIndex;
use Tests\TestCase;

class SeatMapIndexTest extends TestCase
{
    public function test_seats_are_indexed_by_uid_with_area_qualified_labels(): void
    {
        $index = SeatMapIndex::fromLayout($this->fixture('theatre'));

        $this->assertCount(402, $index->seats());
        $this->assertSame('Stalls · A-1', $index->labelOf('e2.0.0'));
        $this->assertSame('b_premium', $index->bandOf('e2.0.0'));
        $this->assertFalse($index->seats()['e2.0.0']['accessible']);
        $this->assertTrue($index->seats()['e6.0.0']['accessible']);
        $this->assertSame('Stalls · Left · A-1', $index->seats()['e4.0.0']['label']);
        $this->assertSame('Balcony · A-1', $index->seats()['e11.0.0']['label']);
        $this->assertSame(['b_premium', 'b_standard', 'b_value'], $index->bandKeys());
    }

    public function test_rows_are_split_into_segments_at_aisles_and_tables_are_exempt(): void
    {
        $theatre = SeatMapIndex::fromLayout($this->fixture('theatre'));
        $firstRow = array_values(array_filter($theatre->segments(), fn (array $segment) => str_starts_with($segment[0], 'e2.0.')));

        $this->assertSame([7, 7, 6], array_map('count', $firstRow));
        $this->assertSame('e2.0.0', $firstRow[0][0]);
        $this->assertSame('e2.0.7', $firstRow[1][0]);

        $this->assertSame([], SeatMapIndex::fromLayout($this->fixture('banquet'))->segments());
    }

    public function test_seats_nearer_the_stage_are_closer_to_the_focal_point(): void
    {
        $index = SeatMapIndex::fromLayout($this->fixture('theatre'));

        $this->assertLessThan($index->distanceToFocalPoint('e3.6.10'), $index->distanceToFocalPoint('e2.0.10'));
    }

    public function test_zones_carry_capacity_and_band(): void
    {
        $index = SeatMapIndex::fromLayout($this->fixture('club'));

        $this->assertSame(
            ['label' => 'Main floor · Front pit', 'band' => 'b_premium', 'capacity' => 220],
            $index->zones()['z2'],
        );
        $this->assertCount(3, $index->zones());
        $this->assertSame('Main floor · B1-1', $index->seats()['e6.0.0']['label']);
    }

    public function test_wheelchair_spaces_and_companion_seats_are_exposed(): void
    {
        $layout = $this->fixture('theatre');
        $layout['areas'][0]['elements'][1]['seats'][0]['comp'] = true;
        $index = SeatMapIndex::fromLayout($layout);

        $this->assertTrue($index->isCompanionSeat('e2.0.0'));
        $this->assertFalse($index->isWheelchairSpace('e2.0.0'));
        $this->assertTrue($index->isWheelchairSpace('e6.0.0'));
        $this->assertFalse($index->isCompanionSeat('e6.0.0'));
        $this->assertFalse($index->isCompanionSeat('e2.0.1'));
        $this->assertTrue($index->seats()['e2.0.0']['companion']);
    }

    public function test_layouts_saved_before_companion_seats_existed_have_none(): void
    {
        $layout = $this->fixture('theatre');
        unset($layout['areas'][0]['elements'][1]['seats'][0]['comp']);

        $this->assertFalse(SeatMapIndex::fromLayout($layout)->isCompanionSeat('e2.0.0'));
    }

    public function test_zones_are_neither_wheelchair_spaces_nor_companion_seats(): void
    {
        $index = SeatMapIndex::fromLayout($this->fixture('club'));

        $this->assertFalse($index->isWheelchairSpace('z2'));
        $this->assertFalse($index->isCompanionSeat('z2'));
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

    /**
     * The orphan rule runs on both sides of the wire, so the runs of adjacent seats it
     * works from have to be derived identically here and in layoutIndex.ts.
     *
     * @see e2e/tests/seating/orphan-rule.unit.spec.ts
     */
    public function test_segments_match_the_shared_fixture_the_frontend_is_pinned_to(): void
    {
        $expected = json_decode(
            file_get_contents(base_path('tests/Fixtures/seating/theatre-segments.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $layout = json_decode(
            file_get_contents(base_path('tests/Fixtures/seating/theatre.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame($expected, SeatMapIndex::fromLayout($layout)->segments());
    }
}
