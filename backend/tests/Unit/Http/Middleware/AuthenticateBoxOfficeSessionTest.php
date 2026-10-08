<?php

namespace Tests\Unit\Http\Middleware;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\CannotSellException;
use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeActivityValidator;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOperatorAuthorizer;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\AuthUserService;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthenticateBoxOfficeSessionTest extends TestCase
{
    private MockInterface|BoxOfficeSessionService $sessionService;

    private MockInterface|BoxOfficeRepositoryInterface $boxOfficeRepository;

    private MockInterface|BoxOfficeActivityValidator $activityValidator;

    private MockInterface|BoxOfficeOperatorAuthorizer $operatorAuthorizer;

    private MockInterface|UserRepositoryInterface $userRepository;

    private MockInterface|AuthManager $authManager;

    private AuthenticateBoxOfficeSession $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionService = Mockery::mock(BoxOfficeSessionService::class);
        $this->boxOfficeRepository = Mockery::mock(BoxOfficeRepositoryInterface::class);
        $this->activityValidator = Mockery::mock(BoxOfficeActivityValidator::class);
        $this->operatorAuthorizer = Mockery::mock(BoxOfficeOperatorAuthorizer::class);
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->authManager = Mockery::mock(AuthManager::class);
        $this->authManager->shouldReceive('check')->andReturnFalse()->byDefault();

        $this->boxOfficeRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->middleware = new AuthenticateBoxOfficeSession(
            $this->sessionService,
            $this->boxOfficeRepository,
            $this->activityValidator,
            $this->operatorAuthorizer,
            $this->userRepository,
            new AuthUserService($this->authManager, Mockery::mock(AccountUserRepositoryInterface::class)),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function request(): Request
    {
        $request = Request::create('/api/public/box-offices/bo_x/products', 'GET', server: ['HTTP_X_BOX_OFFICE_SESSION' => 'bos_t']);
        $route = new Route('GET', '/api/public/box-offices/{box_office_short_id}/products', ['uses' => fn () => null]);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function signedInAs(int $userId): void
    {
        $this->authManager->shouldReceive('check')->andReturnTrue();
        $this->authManager->shouldReceive('id')->andReturn($userId);
    }

    private function boxOffice(?string $pinHash = 'hash'): BoxOfficeDomainObject
    {
        return (new BoxOfficeDomainObject)
            ->setId(12)
            ->setShortId('bo_x')
            ->setPinHash($pinHash)
            ->setEvent((new EventDomainObject)->setId(7));
    }

    private function doorSession(?string $pinHash = 'hash', ?int $userId = null, ?int $occurrenceId = null): BoxOfficeSessionDTO
    {
        return new BoxOfficeSessionDTO(
            box_office_id: 12,
            event_id: 7,
            operator_name: 'Sam',
            event_occurrence_id: $occurrenceId,
            stripe_terminal_reader_id: null,
            pin_hash: $pinHash,
            expires_at: '2030-01-01T00:00:00+00:00',
            authenticated_user_id: $userId,
            authenticated_account_id: $userId === null ? null : 3,
        );
    }

    public function test_a_valid_pin_session_reaches_the_action_with_the_box_office_attached(): void
    {
        $this->sessionService->shouldReceive('resolve')->with('bos_t')->andReturn($this->doorSession());
        $boxOffice = $this->boxOffice();
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->with(['id' => 12])->andReturn($boxOffice);
        $this->activityValidator->shouldReceive('assertActive')->once();

        $request = $this->request();
        $response = $this->middleware->handle($request, fn () => new JsonResponse(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($boxOffice, $request->attributes->get(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE));
    }

    public function test_a_session_from_before_a_pin_reset_is_rejected(): void
    {
        $this->sessionService->shouldReceive('resolve')->andReturn($this->doorSession('old-hash'));
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->andReturn($this->boxOffice('new-hash'));

        $response = $this->middleware->handle($this->request(), fn () => $this->fail('Action must not run'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('BOX_OFFICE_SESSION_EXPIRED', $response->getData(true)['error_code']);
    }

    public function test_an_organizer_who_lost_access_is_signed_out(): void
    {
        $this->sessionService->shouldReceive('resolve')->andReturn($this->doorSession('hash', 21));
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->andReturn($this->boxOffice());
        $this->signedInAs(21);
        $this->userRepository->shouldReceive('findByIdAndAccountId')->with(21, 3)->andReturn((new UserDomainObject)->setId(21));
        $this->operatorAuthorizer->shouldReceive('canOperateWithoutPin')->once()->andReturnFalse();

        $response = $this->middleware->handle($this->request(), fn () => $this->fail('Action must not run'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_an_organizer_still_signed_in_with_access_reaches_the_action(): void
    {
        $this->sessionService->shouldReceive('resolve')->andReturn($this->doorSession('hash', 21));
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->andReturn($this->boxOffice());
        $this->signedInAs(21);
        $this->userRepository->shouldReceive('findByIdAndAccountId')->with(21, 3)->andReturn((new UserDomainObject)->setId(21));
        $this->operatorAuthorizer->shouldReceive('canOperateWithoutPin')->once()->andReturnTrue();
        $this->activityValidator->shouldReceive('assertActive')->once();

        $response = $this->middleware->handle($this->request(), fn () => new JsonResponse(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_an_organizer_session_ends_when_the_organizer_signs_out(): void
    {
        $this->sessionService->shouldReceive('resolve')->andReturn($this->doorSession('hash', 21));
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->andReturn($this->boxOffice());
        $this->operatorAuthorizer->shouldNotReceive('canOperateWithoutPin');

        $response = $this->middleware->handle($this->request(), fn () => $this->fail('Action must not run'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('BOX_OFFICE_SESSION_EXPIRED', $response->getData(true)['error_code']);
    }

    public function test_an_organizer_session_ends_when_someone_else_signs_in_on_the_device(): void
    {
        $this->sessionService->shouldReceive('resolve')->andReturn($this->doorSession('hash', 21));
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->andReturn($this->boxOffice());
        $this->signedInAs(22);
        $this->operatorAuthorizer->shouldNotReceive('canOperateWithoutPin');

        $response = $this->middleware->handle($this->request(), fn () => $this->fail('Action must not run'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_an_organizer_session_ends_when_the_pin_is_reset(): void
    {
        $this->sessionService->shouldReceive('resolve')->andReturn($this->doorSession('old-hash', 21));
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->andReturn($this->boxOffice('new-hash'));
        $this->signedInAs(21);
        $this->operatorAuthorizer->shouldNotReceive('canOperateWithoutPin');

        $response = $this->middleware->handle($this->request(), fn () => $this->fail('Action must not run'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_an_expired_box_office_blocks_every_session_endpoint(): void
    {
        $this->sessionService->shouldReceive('resolve')->andReturn($this->doorSession());
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->andReturn($this->boxOffice());
        $this->activityValidator->shouldReceive('assertActive')->andThrow(new CannotSellException('This box office has expired'));

        $response = $this->middleware->handle($this->request(), fn () => $this->fail('Action must not run'));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('BOX_OFFICE_UNAVAILABLE', $response->getData(true)['error_code']);
    }

    public function test_a_session_on_a_date_other_than_the_pinned_one_is_signed_out(): void
    {
        $this->sessionService->shouldReceive('resolve')->andReturn($this->doorSession(occurrenceId: 30));
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->andReturn($this->boxOffice()->setEventOccurrenceId(31));

        $response = $this->middleware->handle($this->request(), fn () => $this->fail('Action must not run'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('BOX_OFFICE_SESSION_EXPIRED', $response->getData(true)['error_code']);
    }

    public function test_a_session_on_the_pinned_date_is_let_through(): void
    {
        $this->sessionService->shouldReceive('resolve')->andReturn($this->doorSession(occurrenceId: 31));
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->andReturn($this->boxOffice()->setEventOccurrenceId(31));
        $this->activityValidator->shouldReceive('assertActive')->once();

        $response = $this->middleware->handle($this->request(), fn () => new JsonResponse(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
    }
}
