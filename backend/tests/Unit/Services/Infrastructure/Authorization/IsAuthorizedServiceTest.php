<?php

namespace Tests\Unit\Services\Infrastructure\Authorization;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Services\Infrastructure\Authorization\IsAuthorizedService;
use Illuminate\Auth\AuthManager;
use Illuminate\Foundation\Application;
use Mockery as m;
use Tests\TestCase;

class IsAuthorizedServiceTest extends TestCase
{
    private Application $laravelApp;
    private AccountUserRepositoryInterface $accountUserRepository;
    private AuthManager $auth;
    private IsAuthorizedService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laravelApp = m::mock(Application::class);
        $this->accountUserRepository = m::mock(AccountUserRepositoryInterface::class);
        $this->auth = m::mock(AuthManager::class);

        $this->service = new IsAuthorizedService(
            $this->laravelApp,
            $this->accountUserRepository,
            $this->auth
        );
    }

    /**
     * @dataProvider roleDataProvider
     */
    public function testValidateUserRole(Role $minimumRole, string $userRole, bool $shouldPass): void
    {
        $user = m::mock(UserDomainObject::class);
        $accountUser = m::mock(AccountUserDomainObject::class);
        
        $user->shouldReceive('getCurrentAccountUser')->andReturn($accountUser);
        $accountUser->shouldReceive('getRole')->andReturn($userRole);

        if (!$shouldPass) {
            $this->expectException(UnauthorizedException::class);
        }

        $this->service->validateUserRole($minimumRole, $user);

        if ($shouldPass) {
            $this->assertTrue(true);
        }
    }

    public static function roleDataProvider(): array
    {
        return [
            // SUPERADMIN minimum role
            [Role::SUPERADMIN, Role::SUPERADMIN->name, true],
            [Role::SUPERADMIN, Role::ADMIN->name, false],
            [Role::SUPERADMIN, Role::ORGANIZER->name, false],
            [Role::SUPERADMIN, Role::READONLY->name, false],

            // ADMIN minimum role
            [Role::ADMIN, Role::SUPERADMIN->name, true],
            [Role::ADMIN, Role::ADMIN->name, true],
            [Role::ADMIN, Role::ORGANIZER->name, false],
            [Role::ADMIN, Role::READONLY->name, false],

            // ORGANIZER minimum role
            [Role::ORGANIZER, Role::SUPERADMIN->name, true],
            [Role::ORGANIZER, Role::ADMIN->name, true],
            [Role::ORGANIZER, Role::ORGANIZER->name, true],
            [Role::ORGANIZER, Role::READONLY->name, false],

            // READONLY minimum role
            [Role::READONLY, Role::SUPERADMIN->name, true],
            [Role::READONLY, Role::ADMIN->name, true],
            [Role::READONLY, Role::ORGANIZER->name, true],
            [Role::READONLY, Role::READONLY->name, true],
        ];
    }
}
