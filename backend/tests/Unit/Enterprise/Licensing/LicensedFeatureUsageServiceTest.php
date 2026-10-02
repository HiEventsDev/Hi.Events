<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Licensing;

use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\StripeTerminalReaderRepositoryInterface;
use HiEvents\Enterprise\Licensing\DTO\LicenceStateDTO;
use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Enterprise\Licensing\LicenceStatus;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class LicensedFeatureUsageServiceTest extends TestCase
{
    private SeatMapRepositoryInterface&MockInterface $seatMaps;

    private EventSeatMapRepositoryInterface&MockInterface $eventSeatMaps;

    private BoxOfficeRepositoryInterface&MockInterface $boxOffices;

    private StripeTerminalReaderRepositoryInterface&MockInterface $readers;

    private CacheRepository $cache;

    private LicenceService $licenceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seatMaps = Mockery::mock(SeatMapRepositoryInterface::class);
        $this->eventSeatMaps = Mockery::mock(EventSeatMapRepositoryInterface::class);
        $this->boxOffices = Mockery::mock(BoxOfficeRepositoryInterface::class);
        $this->readers = Mockery::mock(StripeTerminalReaderRepositoryInterface::class);
        $this->cache = new CacheRepository(new ArrayStore);
        $this->licenceWithFeatures(LicenceStatus::ACTIVE, [LicensedFeature::SEATING, LicensedFeature::BOX_OFFICE]);
    }

    public function test_nothing_set_up_means_no_features_in_use(): void
    {
        $this->usage(seatMaps: false, eventSeatMaps: false, boxOffices: false, readers: false);

        $this->assertSame([], $this->service()->featuresInUse());
    }

    public function test_a_seat_map_template_means_seating_is_in_use(): void
    {
        $this->usage(seatMaps: true, eventSeatMaps: null, boxOffices: false, readers: false);

        $this->assertSame([LicensedFeature::SEATING], $this->service()->featuresInUse());
    }

    public function test_an_attached_event_seat_map_means_seating_is_in_use(): void
    {
        $this->usage(seatMaps: false, eventSeatMaps: true, boxOffices: false, readers: false);

        $this->assertSame([LicensedFeature::SEATING], $this->service()->featuresInUse());
    }

    public function test_a_card_reader_means_box_office_is_in_use(): void
    {
        $this->usage(seatMaps: false, eventSeatMaps: false, boxOffices: false, readers: true);

        $this->assertSame([LicensedFeature::BOX_OFFICE], $this->service()->featuresInUse());
    }

    public function test_both_features_are_reported(): void
    {
        $this->usage(seatMaps: true, eventSeatMaps: null, boxOffices: true, readers: null);

        $this->assertSame([LicensedFeature::SEATING, LicensedFeature::BOX_OFFICE], $this->service()->featuresInUse());
    }

    public function test_the_result_is_cached_between_calls(): void
    {
        $this->usage(seatMaps: true, eventSeatMaps: null, boxOffices: false, readers: false);
        $service = $this->service();

        $service->featuresInUse();

        $this->assertSame([LicensedFeature::SEATING], $service->featuresInUse());
    }

    public function test_usage_is_scoped_to_an_account_and_cached_separately_from_the_install(): void
    {
        $this->seatMaps->shouldReceive('existsForLiveOrganizer')->with(42)->once()->andReturn(false);
        $this->eventSeatMaps->shouldReceive('existsForUpcomingEvent')->with(42)->once()->andReturn(false);
        $this->boxOffices->shouldReceive('existsInUseForUpcomingEvent')->with(42)->once()->andReturn(true);
        $this->seatMaps->shouldReceive('existsForLiveOrganizer')->with(null)->once()->andReturn(true);
        $this->boxOffices->shouldReceive('existsInUseForUpcomingEvent')->with(null)->once()->andReturn(false);
        $this->readers->shouldReceive('existsForLiveOrganizer')->with(null)->once()->andReturn(false);
        $service = $this->service();

        $this->assertSame([LicensedFeature::BOX_OFFICE], $service->featuresInUse(42));
        $this->assertSame([LicensedFeature::SEATING], $service->featuresInUse());
        $this->assertSame([LicensedFeature::BOX_OFFICE], $service->featuresInUse(42));
    }

    public function test_forget_drops_every_cached_result(): void
    {
        $this->usage(seatMaps: true, eventSeatMaps: null, boxOffices: false, readers: false, times: 2);
        $service = $this->service();

        $service->featuresInUse();
        $service->forget();

        $this->assertSame([LicensedFeature::SEATING], $service->featuresInUse());
    }

    public function test_an_unknown_cached_feature_is_ignored(): void
    {
        $this->cache->put('licensing.features_in_use.0.install', ['seating', 'retired_feature'], 600);

        $this->assertSame([LicensedFeature::SEATING], $this->service()->featuresInUse());
    }

    public function test_a_white_label_licence_counts_white_label_as_in_use_even_once_lapsed(): void
    {
        $this->usage(seatMaps: false, eventSeatMaps: false, boxOffices: false, readers: false);
        $this->licenceWithFeatures(LicenceStatus::LAPSED, [LicensedFeature::WHITE_LABEL]);

        $this->assertSame([LicensedFeature::WHITE_LABEL], $this->service()->featuresInUse());
    }

    private function licenceWithFeatures(LicenceStatus $status, array $features): void
    {
        $licenceService = Mockery::mock(LicenceService::class);
        $licenceService->shouldReceive('state')->andReturn(new LicenceStateDTO(status: $status, features: $features));
        $this->licenceService = $licenceService;
    }

    private function usage(?bool $seatMaps, ?bool $eventSeatMaps, ?bool $boxOffices, ?bool $readers, ?int $accountId = null, int $times = 1): void
    {
        $this->expectCalls($this->seatMaps, 'existsForLiveOrganizer', $seatMaps, $accountId, $times);
        $this->expectCalls($this->eventSeatMaps, 'existsForUpcomingEvent', $eventSeatMaps, $accountId, $times);
        $this->expectCalls($this->boxOffices, 'existsInUseForUpcomingEvent', $boxOffices, $accountId, $times);
        $this->expectCalls($this->readers, 'existsForLiveOrganizer', $readers, $accountId, $times);
    }

    private function expectCalls(MockInterface $mock, string $method, ?bool $result, ?int $accountId, int $times): void
    {
        if ($result === null) {
            $mock->shouldNotReceive($method);

            return;
        }

        $mock->shouldReceive($method)->with($accountId)->times($times)->andReturn($result);
    }

    private function service(): LicensedFeatureUsageService
    {
        return new LicensedFeatureUsageService(
            $this->seatMaps,
            $this->eventSeatMaps,
            $this->boxOffices,
            $this->readers,
            $this->licenceService,
            $this->cache,
        );
    }
}
