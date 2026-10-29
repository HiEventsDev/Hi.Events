<?php

namespace Tests\Unit\Services\Domain\Auth\Access;

use HiEvents\DomainObjects\AccountUserAccessGrantDomainObject;
use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\AccountUserPermissionDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Interfaces\AccountUserAccessGrantRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserPermissionRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccessService;
use HiEvents\Services\Domain\Auth\AuthUserService;
use Mockery as m;
use Mockery\MockInterface;
use Tests\TestCase;

class AccountUserAccessServiceTest extends TestCase
{
    private const ACCOUNT_ID = 10;

    private AuthUserService&MockInterface $authUserService;

    private AccountUserAccessGrantRepositoryInterface&MockInterface $grantRepository;

    private AccountUserPermissionRepositoryInterface&MockInterface $permissionRepository;

    private EventRepositoryInterface&MockInterface $eventRepository;

    private AccountUserAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authUserService = m::mock(AuthUserService::class);
        $this->grantRepository = m::mock(AccountUserAccessGrantRepositoryInterface::class);
        $this->permissionRepository = m::mock(AccountUserPermissionRepositoryInterface::class);
        $this->eventRepository = m::mock(EventRepositoryInterface::class);

        $this->service = new AccountUserAccessService(
            $this->authUserService,
            $this->grantRepository,
            $this->permissionRepository,
            $this->eventRepository,
        );
    }

    public function test_admins_get_every_permission_without_loading_overrides_or_grants(): void
    {
        $this->permissionRepository->shouldNotReceive('findWhereIn');
        $this->grantRepository->shouldNotReceive('findLiveGrantsForAccountUsers');

        $access = $this->service->forAccountUser($this->accountUser(1, 'ADMIN', 'RESTRICTED'));

        $this->assertFalse($access->isRestricted());
        $this->assertEqualsCanonicalizing(Permission::cases(), $access->permissions);
    }

    public function test_account_wide_members_apply_their_overrides_without_loading_grants(): void
    {
        $this->permissionRepository->shouldReceive('findWhereIn')->once()->with('account_user_id', [1])
            ->andReturn(collect([$this->override(1, Permission::VIEW_FINANCIALS, false)]));
        $this->grantRepository->shouldNotReceive('findLiveGrantsForAccountUsers');

        $access = $this->service->forAccountUser($this->accountUser(1, 'ORGANIZER', 'ACCOUNT'));

        $this->assertFalse($access->isRestricted());
        $this->assertSame([Permission::EDIT], $access->permissions);
    }

    public function test_restricted_members_reach_their_organizers_events_and_the_events_parent_organizers(): void
    {
        $this->permissionRepository->shouldReceive('findWhereIn')->andReturn(collect());
        $this->grantRepository->shouldReceive('findLiveGrantsForAccountUsers')->once()->with([1])
            ->andReturn(collect([$this->grant(1, organizerId: 3), $this->grant(1, eventId: 20)]));
        $this->eventRepository->shouldReceive('findWhereIn')->once()->with('id', [20])
            ->andReturn(collect([$this->event(20, organizerId: 4)]));

        $access = $this->service->forAccountUser($this->accountUser(1, 'VIEWER', 'RESTRICTED'));

        $this->assertTrue($access->isRestricted());
        $this->assertSame([3], $access->organizerIds);
        $this->assertSame([20], $access->eventIds);
        $this->assertSame([4], $access->parentOrganizerIds);
        $this->assertTrue($access->canAccessEvent(20, 4));
        $this->assertFalse($access->canAccessEvent(21, 4));
        $this->assertTrue($access->canReadOrganizer(4));
        $this->assertFalse($access->canAccessOrganizer(4));
    }

    public function test_granted_events_from_another_account_are_ignored(): void
    {
        $this->permissionRepository->shouldReceive('findWhereIn')->andReturn(collect());
        $this->grantRepository->shouldReceive('findLiveGrantsForAccountUsers')
            ->andReturn(collect([$this->grant(1, eventId: 20)]));
        $this->eventRepository->shouldReceive('findWhereIn')
            ->andReturn(collect([$this->event(20, organizerId: 4, accountId: 99)]));

        $access = $this->service->forAccountUser($this->accountUser(1, 'ORGANIZER', 'RESTRICTED'));

        $this->assertSame([], $access->eventIds);
        $this->assertSame([], $access->parentOrganizerIds);
        $this->assertFalse($access->canAccessEvent(20, 4));
    }

    public function test_unknown_roles_and_scopes_fail_closed(): void
    {
        $this->permissionRepository->shouldReceive('findWhereIn')->andReturn(collect());
        $this->grantRepository->shouldReceive('findLiveGrantsForAccountUsers')->andReturn(collect());

        $access = $this->service->forAccountUser($this->accountUser(1, 'SOMETHING_ELSE', 'NOT_A_SCOPE'));

        $this->assertSame([], $access->permissions);
        $this->assertTrue($access->isRestricted());
        $this->assertFalse($access->canAccessEvent(20, 4));
    }

    public function test_preload_resolves_many_members_in_one_query_per_table_and_caches_them(): void
    {
        $members = [
            $this->accountUser(1, 'ORGANIZER', 'RESTRICTED'),
            $this->accountUser(2, 'VIEWER', 'RESTRICTED'),
            $this->accountUser(3, 'ORGANIZER', 'ACCOUNT'),
            $this->accountUser(4, 'ADMIN', 'ACCOUNT'),
        ];

        $this->permissionRepository->shouldReceive('findWhereIn')->once()->with('account_user_id', [1, 2, 3])->andReturn(collect());
        $this->grantRepository->shouldReceive('findLiveGrantsForAccountUsers')->once()->with([1, 2])
            ->andReturn(collect([$this->grant(1, eventId: 20), $this->grant(2, eventId: 21)]));
        $this->eventRepository->shouldReceive('findWhereIn')->once()->with('id', [20, 21])
            ->andReturn(collect([$this->event(20, organizerId: 4), $this->event(21, organizerId: 5)]));

        $this->service->preload([...$members, null]);

        $this->assertSame([20], $this->service->forAccountUser($members[0])->eventIds);
        $this->assertSame([21], $this->service->forAccountUser($members[1])->eventIds);
        $this->assertFalse($this->service->forAccountUser($members[2])->isRestricted());
        $this->assertFalse($this->service->forAccountUser($members[3])->isRestricted());
    }

    public function test_forget_reloads_a_member_on_next_use(): void
    {
        $member = $this->accountUser(1, 'ORGANIZER', 'ACCOUNT');
        $this->permissionRepository->shouldReceive('findWhereIn')->twice()->andReturn(
            collect(),
            collect([$this->override(1, Permission::VIEW_FINANCIALS, false)]),
        );

        $this->assertTrue($this->service->forAccountUser($member)->can(Permission::VIEW_FINANCIALS));
        $this->assertTrue($this->service->forAccountUser($member)->can(Permission::VIEW_FINANCIALS));

        $this->service->forget(1);

        $this->assertFalse($this->service->forAccountUser($member)->can(Permission::VIEW_FINANCIALS));
    }

    public function test_the_current_users_access_is_cached_per_token(): void
    {
        $first = $this->accountUser(1, 'ORGANIZER', 'ACCOUNT');
        $second = $this->accountUser(2, 'VIEWER', 'ACCOUNT');
        $this->permissionRepository->shouldReceive('findWhereIn')->andReturn(collect());
        $this->authUserService->shouldReceive('getAuthenticatedTokenKey')->andReturn('token-a', 'token-a', 'token-b');
        $this->authUserService->shouldReceive('getUser')->twice()->andReturn(
            $this->userWith($first),
            $this->userWith($second),
        );

        $this->assertTrue($this->service->forCurrentUser()->can(Permission::EDIT));
        $this->assertTrue($this->service->forCurrentUser()->can(Permission::EDIT));
        $this->assertFalse($this->service->forCurrentUser()->can(Permission::EDIT));
    }

    public function test_system_work_can_run_without_the_current_members_restrictions(): void
    {
        $this->permissionRepository->shouldReceive('findWhereIn')->andReturn(collect());
        $this->authUserService->shouldReceive('getAuthenticatedTokenKey')->andReturn('token-a');
        $this->authUserService->shouldReceive('getUser')->andReturn($this->userWith($this->accountUser(1, 'VIEWER', 'ACCOUNT')));

        $this->assertTrue($this->service->isWithheldFromCurrentUser(Permission::VIEW_FINANCIALS));
        $this->assertFalse($this->service->withoutMemberRestrictions(
            fn () => $this->service->isWithheldFromCurrentUser(Permission::VIEW_FINANCIALS),
        ));
        $this->assertTrue($this->service->isWithheldFromCurrentUser(Permission::VIEW_FINANCIALS));
    }

    public function test_current_member_checks_require_an_active_member_of_the_events_account(): void
    {
        $this->permissionRepository->shouldReceive('findWhereIn')->andReturn(collect());
        $event = $this->event(20, organizerId: 4);

        $this->signInAs($this->accountUser(1, 'ORGANIZER', 'ACCOUNT'), accountId: 99);
        $this->assertFalse($this->service->currentMemberCanAccessEvent($event));

        $this->signInAs($this->accountUser(2, 'ORGANIZER', 'ACCOUNT', UserStatus::INACTIVE));
        $this->assertFalse($this->service->currentMemberCanAccessEvent($event));

        $this->signInAs($this->accountUser(3, 'VIEWER', 'ACCOUNT'));
        $this->assertTrue($this->service->currentMemberCanAccessEvent($event));
        $this->assertFalse($this->service->currentMemberCanAccessEvent($event, Permission::EDIT));

        $this->signInAs($this->accountUser(4, 'ORGANIZER', 'ACCOUNT'));
        $this->assertTrue($this->service->currentMemberCanAccessEvent($event, Permission::EDIT));
    }

    private function signInAs(AccountUserDomainObject $accountUser, int $accountId = self::ACCOUNT_ID): void
    {
        $this->authUserService = m::mock(AuthUserService::class);
        $this->authUserService->shouldReceive('getAuthenticatedAccountId')->andReturn($accountId);
        $this->authUserService->shouldReceive('getUser')->andReturn($this->userWith($accountUser));

        $this->service = new AccountUserAccessService(
            $this->authUserService,
            $this->grantRepository,
            $this->permissionRepository,
            $this->eventRepository,
        );
    }

    private function accountUser(int $id, string $role, string $scope, UserStatus $status = UserStatus::ACTIVE): AccountUserDomainObject
    {
        return (new AccountUserDomainObject)
            ->setId($id)
            ->setAccountId(self::ACCOUNT_ID)
            ->setRole($role)
            ->setAccessScope($scope)
            ->setStatus($status->name);
    }

    private function userWith(AccountUserDomainObject $accountUser): UserDomainObject
    {
        return (new UserDomainObject)->setCurrentAccountUser($accountUser);
    }

    private function grant(int $accountUserId, ?int $organizerId = null, ?int $eventId = null): AccountUserAccessGrantDomainObject
    {
        return (new AccountUserAccessGrantDomainObject)
            ->setAccountUserId($accountUserId)
            ->setOrganizerId($organizerId)
            ->setEventId($eventId);
    }

    private function override(int $accountUserId, Permission $permission, bool $granted): AccountUserPermissionDomainObject
    {
        return (new AccountUserPermissionDomainObject)
            ->setAccountUserId($accountUserId)
            ->setPermission($permission->value)
            ->setGranted($granted);
    }

    private function event(int $id, int $organizerId, int $accountId = self::ACCOUNT_ID): EventDomainObject
    {
        return (new EventDomainObject)
            ->setId($id)
            ->setOrganizerId($organizerId)
            ->setAccountId($accountId);
    }
}
