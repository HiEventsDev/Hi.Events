<?php

namespace Tests\Unit\Services\Application\Handlers\BoxOffice;

use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeBestAvailableSeatsDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeSeatRequestItemDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\GetBoxOfficeBestAvailableSeatsPublicHandler;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Domain\BestAvailableSeatService;
use HiEvents\Enterprise\Seating\Services\Domain\CompanionSeatRule;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\OrphanSeatRule;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatMapIndex;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GetBoxOfficeBestAvailableSeatsPublicHandlerTest extends TestCase
{
    private const ADULT = 1;

    private const CHILD = 2;

    private const STANDARD = 3;

    private EventSeatMapLookupService|MockInterface $lookupService;

    private SeatClaimRepositoryInterface|MockInterface $seatClaimRepository;

    private GetBoxOfficeBestAvailableSeatsPublicHandler $handler;

    private array $layout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->layout = json_decode(file_get_contents(base_path('tests/Fixtures/seating/theatre.json')), true);
        $this->lookupService = Mockery::mock(EventSeatMapLookupService::class);
        $this->lookupService->shouldReceive('indexFor')->with(5)->andReturn(SeatMapIndex::fromLayout($this->layout));
        $seatedProductLookup = Mockery::mock(SeatedProductLookupService::class);
        $seatedProductLookup->shouldReceive('bandKeysByProduct')->with(5)->andReturn([
            self::ADULT => ['b_premium'],
            self::CHILD => ['b_premium'],
            self::STANDARD => ['b_standard'],
        ]);
        $this->seatClaimRepository = Mockery::mock(SeatClaimRepositoryInterface::class);
        $this->handler = new GetBoxOfficeBestAvailableSeatsPublicHandler(
            $this->lookupService,
            $this->seatClaimRepository,
            new BestAvailableSeatService(new OrphanSeatRule, new CompanionSeatRule),
            $seatedProductLookup,
        );
    }

    public function test_ticket_types_sharing_a_band_are_seated_together(): void
    {
        $this->claimed([]);

        [$adults, $children] = $this->handler->handle($this->request([self::ADULT => 2, self::CHILD => 1]));

        $this->assertCount(2, $adults);
        $this->assertCount(1, $children);
        $this->assertSeatsAreConsecutiveInOneRow([...$adults, ...$children]);
    }

    public function test_sold_held_back_and_excluded_seats_are_never_offered(): void
    {
        $taken = ['e2.0.9', 'e2.0.10'];
        $this->claimed($taken);

        [$seats] = $this->handler->handle($this->request([self::ADULT => 4], exclude: ['e2.0.8']));

        $this->assertCount(4, $seats);
        $this->assertEmpty(array_intersect($seats, [...$taken, 'e2.0.8']));
    }

    public function test_items_in_different_bands_are_found_separately(): void
    {
        $this->claimed([]);
        $index = SeatMapIndex::fromLayout($this->layout);

        [$premium, $standard] = $this->handler->handle($this->request([self::ADULT => 2, self::STANDARD => 2]));

        $this->assertSame(['b_premium', 'b_premium'], array_map(fn (string $uid) => $index->bandOf($uid), $premium));
        $this->assertSame(['b_standard', 'b_standard'], array_map(fn (string $uid) => $index->bandOf($uid), $standard));
    }

    public function test_a_band_without_enough_free_seats_returns_nothing_for_its_items(): void
    {
        $this->claimed([]);
        $premiumSeats = SeatMapIndex::fromLayout($this->layout)->capacityByBand()['b_premium'];

        $this->assertSame([[]], $this->handler->handle($this->request([self::ADULT => $premiumSeats + 1])));
    }

    public function test_a_ticket_that_is_not_seated_is_rejected(): void
    {
        $this->claimed([]);

        $this->expectException(SeatSelectionInvalidException::class);

        $this->handler->handle($this->request([99 => 1]));
    }

    private function claimed(array $seatUids): void
    {
        $this->seatClaimRepository->shouldReceive('findTakenSeatUids')->with(7)->andReturn($seatUids);
    }

    private function request(array $quantities, array $exclude = []): BoxOfficeBestAvailableSeatsDTO
    {
        return new BoxOfficeBestAvailableSeatsDTO(
            event_id: 5,
            event_occurrence_id: 7,
            items: array_map(
                fn (int $productId, int $quantity) => new BoxOfficeSeatRequestItemDTO(product_id: $productId, quantity: $quantity),
                array_keys($quantities),
                $quantities,
            ),
            excluded_seat_uids: $exclude,
        );
    }

    private function assertSeatsAreConsecutiveInOneRow(array $seatUids): void
    {
        $positions = array_map(fn (string $uid) => explode('.', $uid), $seatUids);
        $this->assertCount(1, array_unique(array_map(fn (array $parts) => $parts[0].'.'.$parts[1], $positions)));
        $numbers = array_map(fn (array $parts) => (int) $parts[2], $positions);
        sort($numbers);
        $this->assertSame(range($numbers[0], $numbers[0] + count($numbers) - 1), $numbers);
    }
}
