<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class TeamInviteTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private User $owner;

    private int $accountId;

    private int $organizerId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId);
        $this->eventId = $this->createEvent($this->accountId, $this->organizerId);
    }

    public function test_admin_invites_a_member_restricted_to_one_event(): void
    {
        $response = $this->postJson('/users', [
            'first_name' => 'Event',
            'last_name' => 'Helper',
            'email' => 'helper-'.uniqid().'@test.com',
            'role' => Role::VIEWER->value,
            'access_scope' => 'RESTRICTED',
            'event_ids' => [$this->eventId, $this->eventId],
            'permissions' => ['VIEW_FINANCIALS' => true],
        ], $this->ownerHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.access.scope', 'RESTRICTED')
            ->assertJsonPath('data.access.event_ids', [$this->eventId])
            ->assertJsonPath('data.access.permissions', ['VIEW_FINANCIALS']);

        $accountUserId = DB::table('account_users')
            ->where(['account_id' => $this->accountId, 'user_id' => $response->json('data.id')])
            ->value('id');

        $this->assertSame(1, DB::table('account_user_access_grants')->where('account_user_id', $accountUserId)->count());
    }

    public function test_invite_rejects_organizers_and_events_from_another_account(): void
    {
        [, $otherAccountId] = $this->createAccountOwner();
        $foreignOrganizerId = $this->createOrganizer($otherAccountId);

        $this->postJson('/users', [
            'first_name' => 'Event',
            'email' => 'helper-'.uniqid().'@test.com',
            'role' => Role::ORGANIZER->value,
            'access_scope' => 'RESTRICTED',
            'organizer_ids' => [$foreignOrganizerId],
        ], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_scope');

        $this->assertSame(0, DB::table('account_user_access_grants')->where('organizer_id', $foreignOrganizerId)->count());
    }

    public function test_restricted_invite_needs_at_least_one_grant(): void
    {
        $this->postJson('/users', [
            'first_name' => 'Event',
            'email' => 'helper-'.uniqid().'@test.com',
            'role' => Role::ORGANIZER->value,
            'access_scope' => 'RESTRICTED',
        ], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_scope');
    }

    public function test_admins_cannot_be_restricted(): void
    {
        $this->postJson('/users', [
            'first_name' => 'Event',
            'email' => 'helper-'.uniqid().'@test.com',
            'role' => Role::ADMIN->value,
            'access_scope' => 'RESTRICTED',
            'event_ids' => [$this->eventId],
        ], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_scope');
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $this->postJson('/users', [
            'first_name' => 'Event',
            'email' => 'helper-'.uniqid().'@test.com',
            'role' => Role::ORGANIZER->value,
            'permissions' => ['MANAGE_TEAM' => true],
        ], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permissions');
    }

    public function test_oversized_access_lists_and_unknown_permission_keys_are_rejected(): void
    {
        $this->postJson('/users', [
            'first_name' => 'Event',
            'email' => 'helper-'.uniqid().'@test.com',
            'role' => Role::ORGANIZER->value,
            'access_scope' => 'RESTRICTED',
            'organizer_ids' => array_fill(0, 20000, 'x'),
            'permissions' => array_fill_keys(array_map(fn ($i) => "P$i", range(1, 20000)), 'x'),
        ], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organizer_ids', 'permissions']);
    }

    public function test_updating_a_member_without_access_fields_keeps_their_restriction(): void
    {
        $member = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventId]);

        $this->putJson("/users/{$member->id}", [
            'first_name' => 'Renamed',
            'last_name' => 'Member',
            'role' => Role::ORGANIZER->value,
            'status' => 'ACTIVE',
        ], $this->ownerHeaders())->assertOk();

        $this->assertSame('RESTRICTED', DB::table('account_users')->where('id', $this->accountUserId($member, $this->accountId))->value('access_scope'));
    }

    public function test_promoting_a_restricted_member_to_admin_gives_whole_account_access(): void
    {
        $member = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventId]);
        $accountUserId = $this->accountUserId($member, $this->accountId);

        $this->putJson("/users/{$member->id}", [
            'first_name' => 'Promoted',
            'last_name' => 'Member',
            'role' => Role::ADMIN->value,
            'status' => 'ACTIVE',
        ], $this->ownerHeaders())->assertOk();

        $this->assertSame('ACCOUNT', DB::table('account_users')->where('id', $accountUserId)->value('access_scope'));
        $this->assertSame(0, DB::table('account_user_access_grants')->where('account_user_id', $accountUserId)->count());
    }

    public function test_an_invited_member_can_be_given_access_without_sending_a_status(): void
    {
        $invited = $this->addAccountUser($this->accountId, Role::ORGANIZER, UserStatus::INVITED);

        $this->putJson("/users/{$invited->id}", [
            'first_name' => 'Still',
            'last_name' => 'Invited',
            'role' => Role::ORGANIZER->value,
            'access_scope' => 'RESTRICTED',
            'event_ids' => [$this->eventId],
        ], $this->ownerHeaders())->assertOk();

        $accountUser = DB::table('account_users')->where('id', $this->accountUserId($invited, $this->accountId))->first();
        $this->assertSame('INVITED', $accountUser->status);
        $this->assertSame('RESTRICTED', $accountUser->access_scope);
    }

    public function test_revoking_an_invitation_keeps_a_user_who_belongs_to_another_account(): void
    {
        [$victim, $victimAccountId] = $this->createAccountOwner();
        $victim->accounts()->attach($this->accountId, [
            'role' => Role::ORGANIZER->value,
            'status' => UserStatus::INVITED->name,
            'is_account_owner' => false,
        ]);

        $this->deleteJson("/users/{$victim->id}/invitation", [], $this->ownerHeaders())->assertNoContent();

        $this->assertNull(DB::table('users')->where('id', $victim->id)->value('deleted_at'));
        $this->assertNull(DB::table('account_users')->where(['user_id' => $victim->id, 'account_id' => $victimAccountId])->value('deleted_at'));
        $this->assertSame(0, DB::table('account_users')->where(['user_id' => $victim->id, 'account_id' => $this->accountId])->whereNull('deleted_at')->count());
    }

    public function test_revoking_an_invitation_for_a_new_user_removes_them(): void
    {
        $invited = $this->addAccountUser($this->accountId, Role::ORGANIZER, UserStatus::INVITED);

        $this->deleteJson("/users/{$invited->id}/invitation", [], $this->ownerHeaders())->assertNoContent();

        $this->assertNotNull(DB::table('users')->where('id', $invited->id)->value('deleted_at'));
        $this->assertSame(0, DB::table('account_users')->where(['user_id' => $invited->id, 'account_id' => $this->accountId])->whereNull('deleted_at')->count());
    }

    public function test_updating_a_member_rejects_events_from_another_account(): void
    {
        [, $otherAccountId] = $this->createAccountOwner();
        $foreignEventId = $this->createEvent($otherAccountId, $this->createOrganizer($otherAccountId));
        $member = $this->addAccountUser($this->accountId);

        $this->putJson("/users/{$member->id}", [
            'first_name' => 'Member',
            'last_name' => 'One',
            'role' => Role::ORGANIZER->value,
            'access_scope' => 'RESTRICTED',
            'event_ids' => [$foreignEventId],
        ], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_scope');

        $this->assertSame(0, DB::table('account_user_access_grants')->where('event_id', $foreignEventId)->count());
        $this->assertSame('ACCOUNT', DB::table('account_users')->where('id', $this->accountUserId($member, $this->accountId))->value('access_scope'));
    }

    public function test_a_member_cannot_promote_themselves_or_manage_invitations(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $invited = $this->addAccountUser($this->accountId, Role::ORGANIZER, UserStatus::INVITED);
        $headers = $this->headersFor($member, $this->accountId);

        $this->putJson("/users/{$member->id}", [
            'first_name' => 'Sneaky',
            'last_name' => 'Member',
            'role' => Role::ADMIN->value,
        ], $headers)->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_REQUIRED');

        $this->postJson("/users/{$invited->id}/invitation", [], $headers)->assertForbidden();
        $this->deleteJson("/users/{$invited->id}/invitation", [], $headers)->assertForbidden();

        $this->assertSame(Role::ORGANIZER->value, DB::table('account_users')->where('id', $this->accountUserId($member, $this->accountId))->value('role'));
        $this->assertNull(DB::table('users')->where('id', $invited->id)->value('deleted_at'));
    }

    public function test_updating_access_without_a_scope_is_rejected(): void
    {
        $member = $this->addAccountUser($this->accountId);

        $this->putJson("/users/{$member->id}", [
            'first_name' => 'Member',
            'last_name' => 'One',
            'role' => Role::ORGANIZER->value,
            'permissions' => ['VIEW_FINANCIALS' => false],
        ], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_scope');
    }

    public function test_listing_members_does_not_query_per_member(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->organizerId], eventIds: [$this->eventId]);
            $this->addAccountUser($this->accountId, Role::VIEWER);
        }
        $headers = $this->ownerHeaders();

        DB::enableQueryLog();
        $members = $this->getJson('/users', $headers)->assertOk()->json('data');
        DB::disableQueryLog();

        $this->assertCount(11, $members);
        $this->assertLessThanOrEqual(11, count(DB::getQueryLog()));
    }

    public function test_inviting_an_existing_user_in_a_different_case_reuses_their_account_and_hides_their_private_details(): void
    {
        [$existing] = $this->createAccountOwner();
        DB::table('users')->where('id', $existing->id)->update([
            'pending_email' => 'private-'.uniqid().'@test.com',
            'marketing_opted_in_at' => now(),
        ]);

        $response = $this->postJson('/users', [
            'first_name' => 'Anyone',
            'email' => strtoupper($existing->email),
            'role' => Role::VIEWER->value,
        ], $this->ownerHeaders())->assertCreated();

        $this->assertSame($existing->id, $response->json('data.id'));
        $this->assertSame(1, DB::table('users')->whereRaw('lower(email) = ?', [strtolower($existing->email)])->count());
        $this->assertArrayNotHasKey('pending_email', $response->json('data'));
        $this->assertArrayNotHasKey('has_pending_email_change', $response->json('data'));
        $this->assertArrayNotHasKey('marketing_opted_in_at', $response->json('data'));
    }

    public function test_an_invitation_cannot_be_activated_or_used_to_rename_a_user_from_another_account(): void
    {
        [$existing] = $this->createAccountOwner();
        $originalName = DB::table('users')->where('id', $existing->id)->value('first_name');
        $this->postJson('/users', [
            'first_name' => 'Anyone',
            'email' => $existing->email,
            'role' => Role::VIEWER->value,
        ], $this->ownerHeaders())->assertCreated();

        $this->putJson("/users/{$existing->id}", [
            'first_name' => 'Renamed',
            'last_name' => 'ByOtherAccount',
            'role' => Role::ADMIN->value,
            'status' => 'ACTIVE',
        ], $this->ownerHeaders())->assertUnprocessable();

        $this->putJson("/users/{$existing->id}", [
            'first_name' => 'Renamed',
            'last_name' => 'ByOtherAccount',
            'role' => Role::VIEWER->value,
        ], $this->ownerHeaders())->assertOk();

        $this->assertSame($originalName, DB::table('users')->where('id', $existing->id)->value('first_name'));
        $this->assertSame('INVITED', DB::table('account_users')->where(['user_id' => $existing->id, 'account_id' => $this->accountId])->value('status'));
    }

    public function test_a_revoked_invitation_disappears_from_the_team_even_when_the_user_belongs_elsewhere(): void
    {
        [$existing] = $this->createAccountOwner();
        $this->postJson('/users', [
            'first_name' => 'Anyone',
            'email' => $existing->email,
            'role' => Role::VIEWER->value,
        ], $this->ownerHeaders())->assertCreated();

        $this->deleteJson("/users/{$existing->id}/invitation", [], $this->ownerHeaders())->assertNoContent();

        $listed = collect($this->getJson('/users', $this->ownerHeaders())->assertOk()->json('data'))->pluck('id')->all();
        $this->assertNotContains($existing->id, $listed);
    }

    private function ownerHeaders(): array
    {
        return $this->headersFor($this->owner, $this->accountId);
    }
}
