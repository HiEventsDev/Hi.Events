<?php

namespace Tests\Unit\Services\Application\Handlers\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\GetBoxOfficePublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOperatorAuthorizer;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalReaderDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalReadersResponseDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalReaderService;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GetBoxOfficePublicHandlerTest extends TestCase
{
    private MockInterface|StripeTerminalReaderService $readerService;

    private GetBoxOfficePublicHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $boxOfficeRepository = Mockery::mock(BoxOfficeRepositoryInterface::class);
        $boxOfficeRepository->shouldReceive('loadRelation')->andReturnSelf();
        $boxOfficeRepository->shouldReceive('findFirstWhere')->andReturn(
            (new BoxOfficeDomainObject)->setId(1)->setEvent((new EventDomainObject)->setId(7)->setOrganizerId(3)),
        );

        $organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);
        $organizerRepository->shouldReceive('loadRelation')->andReturnSelf();
        $organizerRepository->shouldReceive('findById')->andReturn((new OrganizerDomainObject)->setId(3));

        $operatorAuthorizer = Mockery::mock(BoxOfficeOperatorAuthorizer::class);
        $operatorAuthorizer->shouldReceive('canOperateWithoutPin')->andReturnFalse();

        $this->readerService = Mockery::mock(StripeTerminalReaderService::class);

        $this->handler = new GetBoxOfficePublicHandler(
            $boxOfficeRepository,
            $organizerRepository,
            $this->readerService,
            $operatorAuthorizer,
            new Repository(new ArrayStore(serializesValues: true)),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_the_reader_list_is_fetched_from_stripe_once_per_organizer_while_cached(): void
    {
        $this->readerService->shouldReceive('listForOrganizer')->once()->andReturn(new TerminalReadersResponseDTO(
            readers: collect([
                new TerminalReaderDTO(id: 5, label: 'Front desk', device_type: 'bbpos_wisepos_e', status: 'online', is_available: true),
                new TerminalReaderDTO(id: 6, label: 'Old', device_type: null, status: 'unavailable', is_available: false),
            ]),
            stripe_configured: true,
            stripe_connected: true,
        ));

        $this->handler->handle('bo_x');
        $second = $this->handler->handle('bo_x');

        $this->assertTrue($second->card_payments_enabled);
        $this->assertCount(1, $second->readers);
        $this->assertInstanceOf(TerminalReaderDTO::class, $second->readers->first());
        $this->assertSame('Front desk', $second->readers->first()->label);
    }
}
