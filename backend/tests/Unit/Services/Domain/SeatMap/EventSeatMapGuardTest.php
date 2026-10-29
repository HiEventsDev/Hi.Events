<?php

namespace Tests\Unit\Services\Domain\SeatMap;

use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapChangeConflictException;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapRelabelRequiresConfirmationException;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapGuard;
use HiEvents\Enterprise\Seating\Services\Domain\SeatMapIndex;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EventSeatMapGuardTest extends TestCase
{
    public function test_identical_layouts_produce_an_empty_diff(): void
    {
        $index = SeatMapIndex::fromLayout($this->fixture('theatre'));

        $diff = (new EventSeatMapGuard)->diff($index, $index);

        $this->assertSame(0, $diff->added_seat_count);
        $this->assertSame([], $diff->removed_seat_labels);
        $this->assertSame(0, $diff->relabelled_seat_count);
        $this->assertSame(0, $diff->rebanded_seat_count);
    }

    public function test_diff_reports_added_removed_relabelled_and_rebanded_seats(): void
    {
        $current = $this->fixture('conference');
        $incoming = $current;
        $seats = &$incoming['areas'][0]['elements'][1]['seats'];
        $removed = array_pop($seats);
        $seats[0]['label'] = 'AA-1';
        $seats[1]['band'] = 'b_value';
        $seats[] = ['uid' => 'e2.9.9', 'label' => 'Z-99', 'band' => 'b_premium'] + $removed;

        $diff = (new EventSeatMapGuard)->diff(SeatMapIndex::fromLayout($current), SeatMapIndex::fromLayout($incoming));

        $this->assertSame(1, $diff->added_seat_count);
        $this->assertSame(['Ballroom · E-8'], $diff->removed_seat_labels);
        $this->assertSame(1, $diff->relabelled_seat_count);
        $this->assertSame(1, $diff->rebanded_seat_count);
    }

    public function test_removing_a_linked_band_is_a_conflict(): void
    {
        $layout = $this->fixture('conference');
        $layout['bands'] = array_values(array_filter($layout['bands'], fn (array $band) => $band['key'] !== 'b_value'));

        $this->expectException(SeatMapChangeConflictException::class);

        (new EventSeatMapGuard)->assertLinkedBandsPreserved(
            new Collection([(new EventSeatMapBandProductDomainObject)->setBandKey('b_value')]),
            SeatMapIndex::fromLayout($layout),
        );
    }

    public function test_kept_bands_and_an_empty_eloquent_collection_of_links_pass(): void
    {
        $index = SeatMapIndex::fromLayout($this->fixture('conference'));
        $guard = new EventSeatMapGuard;

        $guard->assertLinkedBandsPreserved(new EloquentCollection, $index);
        $guard->assertLinkedBandsPreserved(
            new Collection([(new EventSeatMapBandProductDomainObject)->setBandKey('b_premium')]),
            $index,
        );

        $this->assertTrue(true);
    }

    public function test_a_claimed_seat_cannot_be_removed_or_rebanded(): void
    {
        $current = $this->fixture('conference');
        $claim = $this->claim('e2.0.0', 'Ballroom · A-1', 'b_premium');

        $removed = $current;
        array_shift($removed['areas'][0]['elements'][1]['seats']);

        $this->expectException(SeatMapChangeConflictException::class);

        (new EventSeatMapGuard)->assertCompatibleWithClaims(
            new Collection([$claim]),
            [],
            SeatMapIndex::fromLayout($removed),
        );
    }

    public function test_renaming_a_claimed_seat_asks_for_confirmation_rather_than_refusing(): void
    {
        $renamed = $this->fixture('conference');
        $renamed['areas'][0]['name'] = 'Great hall';
        $incoming = SeatMapIndex::fromLayout($renamed);
        $claims = new Collection([$this->claim('e2.0.0', 'Ballroom · A-1', 'b_premium')]);
        $guard = new EventSeatMapGuard;

        try {
            $guard->assertCompatibleWithClaims($claims, [], $incoming);
            $this->fail('A rename of a claimed seat should ask for confirmation');
        } catch (SeatMapRelabelRequiresConfirmationException $exception) {
            $this->assertSame(['Ballroom · A-1'], $exception->getSeatLabels());
        }

        $guard->assertCompatibleWithClaims($claims, [], $incoming, allowRelabel: true);

        $this->assertSame(['e2.0.0' => 'Great hall · A-1'], $guard->relabelledSeats($claims, $incoming));
    }

    public function test_an_unchanged_layout_relabels_nothing(): void
    {
        $index = SeatMapIndex::fromLayout($this->fixture('conference'));
        $claims = new Collection([$this->claim('e2.0.0', 'Ballroom · A-1', 'b_premium')]);
        $guard = new EventSeatMapGuard;

        $guard->assertCompatibleWithClaims($claims, [], $index);

        $this->assertSame([], $guard->relabelledSeats($claims, $index));
    }

    private function claim(string $seatUid, string $seatLabel, string $bandKey): object
    {
        return (object) [
            'seat_uid' => $seatUid,
            'seat_label' => $seatLabel,
            'band_key' => $bandKey,
            'is_zone' => false,
            'product_id' => null,
        ];
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
