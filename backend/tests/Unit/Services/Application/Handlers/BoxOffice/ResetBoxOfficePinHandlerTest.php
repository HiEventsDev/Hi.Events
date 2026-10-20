<?php

namespace Tests\Unit\Services\Application\Handlers\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Generated\BoxOfficeDomainObjectAbstract;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\ResetBoxOfficePinHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficePinService;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Exceptions\ResourceNotFoundException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ResetBoxOfficePinHandlerTest extends TestCase
{
    private MockInterface|BoxOfficeRepositoryInterface $boxOfficeRepository;

    private MockInterface|BoxOfficePinService $pinService;

    private ResetBoxOfficePinHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->boxOfficeRepository = Mockery::mock(BoxOfficeRepositoryInterface::class);
        $this->pinService = Mockery::mock(BoxOfficePinService::class);
        $this->handler = new ResetBoxOfficePinHandler($this->boxOfficeRepository, $this->pinService, Mockery::spy(LicensedFeatureUsageService::class));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_throws_when_box_office_does_not_belong_to_event(): void
    {
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->once()->with([
            BoxOfficeDomainObjectAbstract::ID => 3,
            BoxOfficeDomainObjectAbstract::EVENT_ID => 7,
        ])->andReturnNull();
        $this->boxOfficeRepository->shouldNotReceive('updateWhere');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(7, 3);
    }

    public function test_stores_a_fresh_hash_and_returns_the_plain_pin(): void
    {
        $boxOffice = (new BoxOfficeDomainObject)->setId(3)->setEventId(7);
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->twice()->andReturn($boxOffice);
        $this->pinService->shouldReceive('generate')->once()->andReturn('482913');
        $this->pinService->shouldReceive('hash')->once()->with('482913')->andReturn('hashed');
        $this->boxOfficeRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with([BoxOfficeDomainObjectAbstract::PIN_HASH => 'hashed'], [
                BoxOfficeDomainObjectAbstract::ID => 3,
                BoxOfficeDomainObjectAbstract::EVENT_ID => 7,
            ]);

        $result = $this->handler->handle(7, 3);

        $this->assertSame('482913', $result->pin);
        $this->assertSame($boxOffice, $result->box_office);
    }
}
