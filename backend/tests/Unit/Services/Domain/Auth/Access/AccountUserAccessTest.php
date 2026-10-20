<?php

namespace Tests\Unit\Services\Domain\Auth\Access;

use HiEvents\DomainObjects\Enums\AccountUserAccessScope;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccess;
use Tests\TestCase;

class AccountUserAccessTest extends TestCase
{
    public function test_admins_get_every_permission_and_ignore_overrides(): void
    {
        $permissions = Role::ADMIN->permissionsWith(['VIEW_FINANCIALS' => false]);

        $this->assertEqualsCanonicalizing(Permission::cases(), $permissions);
    }

    public function test_organizers_default_to_editing_financials_and_refunds(): void
    {
        $this->assertEqualsCanonicalizing(
            [Permission::EDIT, Permission::VIEW_FINANCIALS, Permission::ISSUE_REFUNDS],
            Role::ORGANIZER->permissionsWith([]),
        );
    }

    public function test_removing_financials_also_removes_refunds(): void
    {
        $this->assertSame(
            [Permission::EDIT],
            Role::ORGANIZER->permissionsWith(['VIEW_FINANCIALS' => false]),
        );
    }

    public function test_viewers_can_be_given_financials_but_never_refunds(): void
    {
        $this->assertSame(
            [Permission::VIEW_FINANCIALS],
            Role::VIEWER->permissionsWith(['VIEW_FINANCIALS' => true, 'ISSUE_REFUNDS' => true]),
        );
    }

    public function test_non_adjustable_permissions_cannot_be_granted_by_override(): void
    {
        $this->assertSame(
            [],
            Role::VIEWER->permissionsWith(['MANAGE_TEAM' => true, 'EDIT' => true]),
        );
    }

    public function test_account_wide_access_reaches_everything(): void
    {
        $access = new AccountUserAccess(AccountUserAccessScope::ACCOUNT, []);

        $this->assertTrue($access->canAccessOrganizer(5));
        $this->assertTrue($access->canAccessEvent(9, 5));
        $this->assertTrue($access->canAccessEvent(9, null));
    }

    public function test_restricted_access_follows_grants(): void
    {
        $access = new AccountUserAccess(
            scope: AccountUserAccessScope::RESTRICTED,
            permissions: [],
            organizerIds: [1],
            eventIds: [20],
            parentOrganizerIds: [2],
        );

        $this->assertTrue($access->canAccessOrganizer(1));
        $this->assertFalse($access->canAccessOrganizer(2));
        $this->assertTrue($access->canReadOrganizer(2));
        $this->assertFalse($access->canReadOrganizer(3));

        $this->assertTrue($access->canAccessEvent(10, 1));
        $this->assertTrue($access->canAccessEvent(20, 2));
        $this->assertFalse($access->canAccessEvent(21, 2));
        $this->assertFalse($access->canAccessEvent(22, null));

        $this->assertEqualsCanonicalizing([1, 2], $access->readableOrganizerIds());
    }

    public function test_restricted_access_with_no_grants_reaches_nothing(): void
    {
        $access = new AccountUserAccess(AccountUserAccessScope::RESTRICTED, []);

        $this->assertFalse($access->canAccessOrganizer(1));
        $this->assertFalse($access->canAccessEvent(1, 1));
        $this->assertSame([], $access->readableOrganizerIds());
    }
}
