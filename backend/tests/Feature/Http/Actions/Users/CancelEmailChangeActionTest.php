<?php

namespace Tests\Feature\Http\Actions\Users;

use HiEvents\DomainObjects\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class CancelEmailChangeActionTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_user_can_cancel_their_own_pending_email_change(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        DB::table('users')->where('id', $owner->id)->update(['pending_email' => 'new-'.uniqid().'@test.com']);

        $this->deleteJson("/users/{$owner->id}/email-change", [], $this->headersFor($owner, $accountId))
            ->assertOk();

        $this->assertNull(DB::table('users')->where('id', $owner->id)->value('pending_email'));
    }

    public function test_user_cannot_cancel_another_members_pending_email_change(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $pendingEmail = 'new-'.uniqid().'@test.com';
        DB::table('users')->where('id', $owner->id)->update(['pending_email' => $pendingEmail]);

        $organizer = $this->addAccountUser($accountId, Role::ORGANIZER);

        $this->deleteJson("/users/{$owner->id}/email-change", [], $this->headersFor($organizer, $accountId))
            ->assertForbidden();

        $this->assertSame($pendingEmail, DB::table('users')->where('id', $owner->id)->value('pending_email'));
    }
}
