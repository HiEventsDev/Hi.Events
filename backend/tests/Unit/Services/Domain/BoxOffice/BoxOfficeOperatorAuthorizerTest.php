<?php

namespace Tests\Unit\Services\Domain\BoxOffice;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOperatorAuthorizer;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Infrastructure\Authorization\IsAuthorizedService;
use Illuminate\Auth\AuthManager;
use Illuminate\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BoxOfficeOperatorAuthorizerTest extends TestCase
{
    private const EVENT_ID = 88;

    private const ACCOUNT_ID = 3;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private AuthManager|MockInterface $authManager;

    private BoxOfficeOperatorAuthorizer $authorizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->authManager = Mockery::mock(AuthManager::class);

        $container = Mockery::mock(Application::class);
        $container->shouldReceive('make')
            ->with(EventRepositoryInterface::class)
            ->andReturn($this->eventRepository);

        $this->authorizer = new BoxOfficeOperatorAuthorizer(new IsAuthorizedService(
            $container,
            Mockery::mock(AccountUserRepositoryInterface::class),
            $this->authManager,
        ));
    }

    private function event(int $accountId = self::ACCOUNT_ID): EventDomainObject
    {
        return (new EventDomainObject)->setId(self::EVENT_ID)->setAccountId($accountId);
    }

    private function user(string $status = UserStatus::ACTIVE->name, string $role = Role::ORGANIZER->name): UserDomainObject
    {
        return (new UserDomainObject)
            ->setId(21)
            ->setCurrentAccountUser(
                (new AccountUserDomainObject)->setStatus($status)->setRole($role)
            );
    }

    public function test_a_user_on_the_events_account_can_operate_without_a_pin(): void
    {
        $this->eventRepository->shouldReceive('findById')
            ->with(self::EVENT_ID)
            ->andReturn($this->event());

        $this->assertTrue(
            $this->authorizer->canOperateWithoutPin($this->event(), $this->user(), self::ACCOUNT_ID)
        );
    }

    public function test_a_user_from_another_account_cannot_operate_without_a_pin(): void
    {
        $this->eventRepository->shouldReceive('findById')
            ->with(self::EVENT_ID)
            ->andReturn($this->event());

        $this->assertFalse(
            $this->authorizer->canOperateWithoutPin($this->event(), $this->user(), 99)
        );
    }

    public function test_a_deactivated_user_cannot_operate_without_a_pin(): void
    {
        $this->authManager->shouldReceive('logout');

        $this->assertFalse(
            $this->authorizer->canOperateWithoutPin(
                $this->event(),
                $this->user(status: UserStatus::INACTIVE->name),
                self::ACCOUNT_ID,
            )
        );
    }

    public function test_an_anonymous_viewer_cannot_operate_without_a_pin(): void
    {
        $this->assertFalse($this->authorizer->canOperateWithoutPin($this->event(), null, null));
        $this->assertFalse($this->authorizer->canOperateWithoutPin($this->event(), $this->user(), null));
        $this->assertFalse($this->authorizer->canOperateWithoutPin($this->event(), null, self::ACCOUNT_ID));
    }
}
