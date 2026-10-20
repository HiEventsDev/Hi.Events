<?php

namespace Tests\Unit\Services\Application\Handlers\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\StripeTerminalReaderDomainObject;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\StripeTerminalReaderRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\UpdateBoxOfficeSessionDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\UpdateBoxOfficeSessionPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeReaderResolutionService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionScopeService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionScopeDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalReaderDTO;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateBoxOfficeSessionPublicHandlerTest extends TestCase
{
    private MockInterface|BoxOfficeSessionScopeService $scopeService;

    private MockInterface|BoxOfficeSessionService $sessionService;

    private MockInterface|BoxOfficeReaderResolutionService $readerResolution;

    private MockInterface|StripeTerminalReaderRepositoryInterface $readerRepository;

    private UpdateBoxOfficeSessionPublicHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scopeService = Mockery::mock(BoxOfficeSessionScopeService::class);
        $this->sessionService = Mockery::mock(BoxOfficeSessionService::class);
        $this->readerResolution = Mockery::mock(BoxOfficeReaderResolutionService::class);
        $this->readerRepository = Mockery::mock(StripeTerminalReaderRepositoryInterface::class);
        $this->handler = new UpdateBoxOfficeSessionPublicHandler(
            scopeService: $this->scopeService,
            sessionService: $this->sessionService,
            readerResolution: $this->readerResolution,
            readerRepository: $this->readerRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function storedSession(): BoxOfficeSessionDTO
    {
        return new BoxOfficeSessionDTO(
            box_office_id: 12,
            event_id: 7,
            operator_name: 'Sam',
            event_occurrence_id: 1,
            stripe_terminal_reader_id: 9,
            pin_hash: 'hash',
            expires_at: '2030-01-01T00:00:00+00:00',
        );
    }

    private function dto(): UpdateBoxOfficeSessionDTO
    {
        return new UpdateBoxOfficeSessionDTO(
            box_office_short_id: 'bo_x',
            token: 'bos_token',
            session: $this->storedSession(),
            event_occurrence_id: 2,
        );
    }

    private function boxOffice(): BoxOfficeDomainObject
    {
        $boxOffice = (new BoxOfficeDomainObject)->setId(12)->setEventId(7);
        $boxOffice->setEvent((new EventDomainObject)->setId(7)->setOrganizerId(3));

        return $boxOffice;
    }

    public function test_rejects_switching_on_a_fixed_date_box_office(): void
    {
        $boxOffice = $this->boxOffice();
        $this->scopeService->shouldReceive('loadBoxOffice')->with('bo_x')->andReturn($boxOffice);
        $this->scopeService->shouldReceive('canSwitchOccurrence')->with($boxOffice)->andReturnFalse();
        $this->sessionService->shouldNotReceive('update');

        $this->expectException(ValidationException::class);

        $this->handler->handle($this->dto());
    }

    public function test_rewrites_the_session_with_the_new_occurrence_and_keeps_everything_else(): void
    {
        $boxOffice = $this->boxOffice();
        $occurrence = (new EventOccurrenceDomainObject)->setId(2);
        $this->scopeService->shouldReceive('loadBoxOffice')->andReturn($boxOffice);
        $this->scopeService->shouldReceive('canSwitchOccurrence')->andReturnTrue();
        $this->scopeService->shouldReceive('resolve')->with($boxOffice, 2)->andReturn(new BoxOfficeSessionScopeDTO(
            event_occurrence: $occurrence,
            check_in_list_short_id: 'cil_x',
            check_in_available: true,
            check_in_unavailable_reason: null,
        ));
        $this->sessionService
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (string $token, BoxOfficeSessionDTO $session) => $token === 'bos_token'
                && $session->event_occurrence_id === 2
                && $session->stripe_terminal_reader_id === 9
                && $session->operator_name === 'Sam'
                && $session->pin_hash === 'hash'
                && $session->expires_at === '2030-01-01T00:00:00+00:00');
        $this->readerRepository->shouldReceive('findFirstWhere')->with(['id' => 9])
            ->andReturn((new StripeTerminalReaderDomainObject)->setId(9)->setLabel('Front door'));

        $result = $this->handler->handle($this->dto());

        $this->assertSame('bos_token', $result->token);
        $this->assertSame($occurrence, $result->event_occurrence);
        $this->assertSame('Front door', $result->reader->label);
        $this->assertSame('cil_x', $result->check_in_list_short_id);
        $this->assertTrue($result->check_in_available);
    }

    public function test_changes_the_reader_and_keeps_the_occurrence(): void
    {
        $boxOffice = $this->boxOffice();
        $occurrence = (new EventOccurrenceDomainObject)->setId(1);
        $this->scopeService->shouldReceive('loadBoxOffice')->andReturn($boxOffice);
        $this->scopeService->shouldNotReceive('canSwitchOccurrence');
        $this->scopeService->shouldReceive('resolve')->with($boxOffice, 1)->andReturn(new BoxOfficeSessionScopeDTO(
            event_occurrence: $occurrence,
            check_in_list_short_id: null,
            check_in_available: false,
            check_in_unavailable_reason: 'none',
        ));
        $this->readerResolution
            ->shouldReceive('resolve')
            ->once()
            ->with($boxOffice->getEvent(), 4)
            ->andReturn(new TerminalReaderDTO(id: 4, label: 'Side door', device_type: 'stripe_s710', status: 'online', is_available: true));
        $this->sessionService
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (string $token, BoxOfficeSessionDTO $session) => $session->event_occurrence_id === 1
                && $session->stripe_terminal_reader_id === 4);
        $this->readerRepository->shouldNotReceive('findFirstWhere');

        $result = $this->handler->handle(new UpdateBoxOfficeSessionDTO(
            box_office_short_id: 'bo_x',
            token: 'bos_token',
            session: $this->storedSession(),
            change_reader: true,
            stripe_terminal_reader_id: 4,
        ));

        $this->assertSame(4, $result->reader->id);
        $this->assertSame('Side door', $result->reader->label);
    }

    public function test_clears_the_reader_when_asked(): void
    {
        $boxOffice = $this->boxOffice();
        $this->scopeService->shouldReceive('loadBoxOffice')->andReturn($boxOffice);
        $this->scopeService->shouldReceive('resolve')->andReturn(new BoxOfficeSessionScopeDTO(
            event_occurrence: (new EventOccurrenceDomainObject)->setId(1),
            check_in_list_short_id: null,
            check_in_available: false,
            check_in_unavailable_reason: 'none',
        ));
        $this->readerResolution->shouldReceive('resolve')->once()->with($boxOffice->getEvent(), null)->andReturnNull();
        $this->sessionService
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (string $token, BoxOfficeSessionDTO $session) => $session->stripe_terminal_reader_id === null);

        $result = $this->handler->handle(new UpdateBoxOfficeSessionDTO(
            box_office_short_id: 'bo_x',
            token: 'bos_token',
            session: $this->storedSession(),
            change_reader: true,
            stripe_terminal_reader_id: null,
        ));

        $this->assertNull($result->reader);
    }

    public function test_keeps_the_organizer_identity_on_an_authenticated_session(): void
    {
        $boxOffice = $this->boxOffice();
        $this->scopeService->shouldReceive('loadBoxOffice')->andReturn($boxOffice);
        $this->scopeService->shouldReceive('canSwitchOccurrence')->andReturnTrue();
        $this->scopeService->shouldReceive('resolve')->andReturn(new BoxOfficeSessionScopeDTO(
            event_occurrence: (new EventOccurrenceDomainObject)->setId(2),
            check_in_list_short_id: null,
            check_in_available: false,
            check_in_unavailable_reason: null,
        ));
        $this->readerRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->sessionService
            ->shouldReceive('update')
            ->once()
            ->withArgs(fn (string $token, BoxOfficeSessionDTO $session) => $session->authenticated_user_id === 21
                && $session->authenticated_account_id === 3
                && $session->pin_hash === null);

        $result = $this->handler->handle(new UpdateBoxOfficeSessionDTO(
            box_office_short_id: 'bo_x',
            token: 'bos_token',
            session: new BoxOfficeSessionDTO(
                box_office_id: 12,
                event_id: 7,
                operator_name: 'Dave',
                event_occurrence_id: 1,
                stripe_terminal_reader_id: null,
                pin_hash: null,
                expires_at: '2030-01-01T00:00:00+00:00',
                authenticated_user_id: 21,
                authenticated_account_id: 3,
            ),
            event_occurrence_id: 2,
        ));

        $this->assertSame('Dave', $result->operator_name);
    }
}
