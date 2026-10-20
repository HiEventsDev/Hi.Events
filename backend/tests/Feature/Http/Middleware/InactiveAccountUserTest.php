<?php

namespace Tests\Feature\Http\Middleware;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class InactiveAccountUserTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_deactivated_member_cannot_list_events(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $member = $this->addAccountUser($accountId, Role::ORGANIZER);

        DB::table('account_users')
            ->where(['account_id' => $accountId, 'user_id' => $member->id])
            ->update(['status' => UserStatus::INACTIVE->name]);

        $this->getJson('/events', $this->headersFor($member, $accountId))->assertForbidden();
        $this->getJson('/organizers', $this->headersFor($member, $accountId))->assertForbidden();
        $this->postJson('/organizers', ['name' => 'Sneaky', 'email' => 'sneaky@test.com', 'currency' => 'USD', 'timezone' => 'UTC'], $this->headersFor($member, $accountId))
            ->assertForbidden();
    }

    public function test_active_member_can_list_events(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $member = $this->addAccountUser($accountId, Role::ORGANIZER);

        $this->getJson('/events', $this->headersFor($member, $accountId))->assertOk();
    }
}
