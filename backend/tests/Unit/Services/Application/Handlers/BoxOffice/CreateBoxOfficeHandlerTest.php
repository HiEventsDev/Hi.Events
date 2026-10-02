<?php

namespace Tests\Unit\Services\Application\Handlers\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\CreateBoxOfficeHandler;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO\UpsertBoxOfficeDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficePinService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\CreateBoxOfficeService;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use Mockery;
use Tests\TestCase;

class CreateBoxOfficeHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_generates_a_pin_and_returns_it_with_the_box_office(): void
    {
        $created = (new BoxOfficeDomainObject)->setId(9);
        $pinService = Mockery::mock(BoxOfficePinService::class);
        $pinService->shouldReceive('generate')->once()->andReturn('123456');

        $createService = Mockery::mock(CreateBoxOfficeService::class);
        $createService
            ->shouldReceive('createBoxOffice')
            ->once()
            ->withArgs(fn (BoxOfficeDomainObject $boxOffice, array $productIds, ?string $pin) => $boxOffice->getName() === 'West gate'
                && $boxOffice->getEventId() === 7
                && $productIds === [1]
                && $pin === '123456')
            ->andReturn($created);

        $result = (new CreateBoxOfficeHandler($createService, $pinService, Mockery::spy(LicensedFeatureUsageService::class)))->handle(new UpsertBoxOfficeDTO(
            name: 'West gate',
            description: null,
            event_id: 7,
            product_ids: [1],
        ));

        $this->assertSame('123456', $result->pin);
        $this->assertSame($created, $result->box_office);
    }
}
