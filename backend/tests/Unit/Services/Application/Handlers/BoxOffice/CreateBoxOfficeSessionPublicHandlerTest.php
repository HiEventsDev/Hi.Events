<?php

namespace Tests\Unit\Services\Application\Handlers\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\TooManyPinAttemptsException;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\CreateBoxOfficeSessionPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\CreateBoxOfficeSessionDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeActivityValidator;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOperatorAuthorizer;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficePinAttemptLimiter;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficePinService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeReaderResolutionService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionScopeService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionScopeDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\CreatedBoxOfficeSessionDTO;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateBoxOfficeSessionPublicHandlerTest extends TestCase
{
    private MockInterface|BoxOfficeSessionScopeService $scopeService;

    private MockInterface|BoxOfficePinService $pinService;

    private MockInterface|BoxOfficeSessionService $sessionService;

    private MockInterface|BoxOfficeOperatorAuthorizer $operatorAuthorizer;

    private MockInterface|BoxOfficePinAttemptLimiter $pinAttemptLimiter;

    private CreateBoxOfficeSessionPublicHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scopeService = Mockery::mock(BoxOfficeSessionScopeService::class);
        $this->pinService = Mockery::mock(BoxOfficePinService::class);
        $this->sessionService = Mockery::mock(BoxOfficeSessionService::class);
        $this->operatorAuthorizer = Mockery::mock(BoxOfficeOperatorAuthorizer::class);
        $this->pinAttemptLimiter = Mockery::mock(BoxOfficePinAttemptLimiter::class);

        $activityValidator = Mockery::mock(BoxOfficeActivityValidator::class);
        $activityValidator->shouldReceive('assertActive');

        $readerResolution = Mockery::mock(BoxOfficeReaderResolutionService::class);
        $readerResolution->shouldReceive('resolve')->andReturnNull();

        $this->handler = new CreateBoxOfficeSessionPublicHandler(
            scopeService: $this->scopeService,
            activityValidator: $activityValidator,
            pinService: $this->pinService,
            sessionService: $this->sessionService,
            readerResolution: $readerResolution,
            operatorAuthorizer: $this->operatorAuthorizer,
            pinAttemptLimiter: $this->pinAttemptLimiter,
            featureFlagService: Mockery::mock(FeatureFlagService::class)->shouldIgnoreMissing(),
        );

        $boxOffice = (new BoxOfficeDomainObject)
            ->setId(12)
            ->setEventId(7)
            ->setPinHash('hash')
            ->setEvent((new EventDomainObject)->setId(7)->setAccountId(3));

        $this->scopeService->shouldReceive('loadBoxOffice')->andReturn($boxOffice);
        $this->scopeService->shouldReceive('resolve')->andReturn(new BoxOfficeSessionScopeDTO(
            event_occurrence: null,
            check_in_list_short_id: null,
            check_in_available: false,
            check_in_unavailable_reason: null,
        ));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function expectSessionCreatedWithUserId(?int $userId): void
    {
        $this->sessionService->shouldReceive('create')
            ->once()
            ->withArgs(fn (...$args) => $args[4] === $userId)
            ->andReturn(new CreatedBoxOfficeSessionDTO(
                token: 'bos_test',
                session: new BoxOfficeSessionDTO(
                    box_office_id: 12,
                    event_id: 7,
                    operator_name: 'Sam',
                    event_occurrence_id: null,
                    stripe_terminal_reader_id: null,
                    pin_hash: $userId === null ? 'hash' : null,
                    expires_at: '2026-01-01T00:00:00+00:00',
                    authenticated_user_id: $userId,
                ),
            ));
    }

    public function test_an_authorized_user_starts_a_session_without_a_pin(): void
    {
        $this->operatorAuthorizer->shouldReceive('canOperateWithoutPin')->andReturnTrue();
        $this->pinService->shouldNotReceive('verify');
        $this->expectSessionCreatedWithUserId(21);

        $session = $this->handler->handle(new CreateBoxOfficeSessionDTO(
            box_office_short_id: 'bo_test',
            operator_name: 'Sam',
            ip_address: '127.0.0.1',
            authenticated_user: (new UserDomainObject)->setId(21),
            authenticated_account_id: 3,
        ));

        $this->assertSame('bos_test', $session->token);
    }

    public function test_an_authorized_user_who_submits_a_pin_hands_over_to_staff(): void
    {
        $this->operatorAuthorizer->shouldNotReceive('canOperateWithoutPin');
        $this->pinService->shouldReceive('verify')->with('1234', 'hash')->once()->andReturnTrue();
        $this->pinAttemptLimiter->shouldReceive('reserveAttempt')->once()->with(Mockery::on(fn (BoxOfficeDomainObject $boxOffice) => $boxOffice->getId() === 12), '127.0.0.1');
        $this->pinAttemptLimiter->shouldReceive('recordSuccess')->once()->with(Mockery::on(fn (BoxOfficeDomainObject $boxOffice) => $boxOffice->getId() === 12), '127.0.0.1');
        $this->expectSessionCreatedWithUserId(null);

        $session = $this->handler->handle(new CreateBoxOfficeSessionDTO(
            box_office_short_id: 'bo_test',
            operator_name: 'Sam',
            ip_address: '127.0.0.1',
            pin: '1234',
            authenticated_user: (new UserDomainObject)->setId(21),
            authenticated_account_id: 3,
        ));

        $this->assertSame('bos_test', $session->token);
    }

    public function test_an_authorized_user_who_submits_a_wrong_pin_is_rejected(): void
    {
        $this->pinService->shouldReceive('verify')->with('9999', 'hash')->once()->andReturnFalse();
        $this->pinAttemptLimiter->shouldReceive('reserveAttempt')->once()->with(Mockery::on(fn (BoxOfficeDomainObject $boxOffice) => $boxOffice->getId() === 12), '127.0.0.1');
        $this->pinAttemptLimiter->shouldNotReceive('recordSuccess');
        $this->sessionService->shouldNotReceive('create');

        $this->expectException(UnauthorizedException::class);

        $this->handler->handle(new CreateBoxOfficeSessionDTO(
            box_office_short_id: 'bo_test',
            operator_name: 'Sam',
            ip_address: '127.0.0.1',
            pin: '9999',
            authenticated_user: (new UserDomainObject)->setId(21),
            authenticated_account_id: 3,
        ));
    }

    public function test_an_unauthorized_viewer_still_needs_a_valid_pin(): void
    {
        $this->operatorAuthorizer->shouldReceive('canOperateWithoutPin')->andReturnFalse();
        $this->pinService->shouldReceive('verify')->with('1234', 'hash')->once()->andReturnTrue();
        $this->pinAttemptLimiter->shouldReceive('reserveAttempt')->once();
        $this->pinAttemptLimiter->shouldReceive('recordSuccess')->once();
        $this->expectSessionCreatedWithUserId(null);

        $session = $this->handler->handle(new CreateBoxOfficeSessionDTO(
            box_office_short_id: 'bo_test',
            operator_name: 'Sam',
            ip_address: '127.0.0.1',
            pin: '1234',
        ));

        $this->assertSame('bos_test', $session->token);
    }

    public function test_an_unauthorized_viewer_without_a_pin_is_rejected(): void
    {
        $this->operatorAuthorizer->shouldReceive('canOperateWithoutPin')->andReturnFalse();
        $this->pinService->shouldNotReceive('verify');
        $this->sessionService->shouldNotReceive('create');

        $this->expectException(UnauthorizedException::class);

        $this->handler->handle(new CreateBoxOfficeSessionDTO(
            box_office_short_id: 'bo_test',
            operator_name: 'Sam',
            ip_address: '127.0.0.1',
        ));
    }

    public function test_a_locked_out_device_is_refused_before_the_pin_is_checked(): void
    {
        $this->operatorAuthorizer->shouldReceive('canOperateWithoutPin')->andReturnFalse();
        $this->pinAttemptLimiter->shouldReceive('reserveAttempt')->once()->andThrow(new TooManyPinAttemptsException('Locked'));
        $this->pinService->shouldNotReceive('verify');
        $this->sessionService->shouldNotReceive('create');

        $this->expectException(TooManyPinAttemptsException::class);

        $this->handler->handle(new CreateBoxOfficeSessionDTO(
            box_office_short_id: 'bo_test',
            operator_name: 'Sam',
            ip_address: '127.0.0.1',
            pin: '1234',
        ));
    }
}
