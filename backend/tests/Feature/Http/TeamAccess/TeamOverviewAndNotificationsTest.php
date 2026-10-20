<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Mail\Account\AdminAccessGrantedMail;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Mail\TeamOrderNotificationRecipientService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class TeamOverviewAndNotificationsTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private User $owner;

    private int $accountId;

    private int $organizerId;

    private int $otherOrganizerId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId);
        $this->otherOrganizerId = $this->createOrganizer($this->accountId, 'Other');
        $this->eventId = $this->createEvent($this->accountId, $this->organizerId);
    }

    public function test_event_team_lists_everyone_with_access_and_how(): void
    {
        $organizerMember = $this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->organizerId]);
        $eventMember = $this->addRestrictedAccountUser($this->accountId, Role::VIEWER, eventIds: [$this->eventId]);
        $unrelated = $this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->otherOrganizerId]);

        $team = collect($this->getJson("/events/{$this->eventId}/team", $this->headersFor($this->owner, $this->accountId))
            ->assertOk()
            ->json('data'))
            ->pluck('access_via', 'user_id');

        $this->assertSame('ACCOUNT', $team[$this->owner->id]);
        $this->assertSame('ORGANIZER', $team[$organizerMember->id]);
        $this->assertSame('EVENT', $team[$eventMember->id]);
        $this->assertArrayNotHasKey($unrelated->id, $team->all());
    }

    public function test_organizer_team_lists_organizer_level_access_only(): void
    {
        $organizerMember = $this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->organizerId]);
        $eventMember = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventId]);

        $team = collect($this->getJson("/organizers/{$this->organizerId}/team", $this->headersFor($this->owner, $this->accountId))
            ->assertOk()
            ->json('data'))
            ->pluck('access_via', 'user_id');

        $this->assertSame('ORGANIZER', $team[$organizerMember->id]);
        $this->assertArrayNotHasKey($eventMember->id, $team->all());
    }

    public function test_team_overview_is_admin_only(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->getJson("/events/{$this->eventId}/team", $this->headersFor($organizer, $this->accountId))->assertForbidden();
    }

    public function test_members_can_opt_in_to_order_notifications(): void
    {
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->putJson('/users/me', ['notify_new_orders' => true], $this->headersFor($member, $this->accountId))
            ->assertOk()
            ->assertJsonPath('data.notify_new_orders', true);

        $this->assertTrue((bool) DB::table('account_users')->where('id', $this->accountUserId($member, $this->accountId))->value('notify_new_orders'));
    }

    public function test_order_notifications_reach_only_opted_in_members_who_can_see_the_event_and_its_money(): void
    {
        $subscribed = $this->subscribedMember($this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventId]));
        $this->subscribedMember($this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->otherOrganizerId]));
        $this->subscribedMember($this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventId], permissions: ['VIEW_FINANCIALS' => false]));
        $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->subscribedMember($this->addAccountUser($this->accountId, Role::ORGANIZER, UserStatus::INACTIVE));
        $this->subscribedMember($this->addAccountUser($this->accountId, Role::VIEWER));
        $unverified = $this->subscribedMember($this->addAccountUser($this->accountId, Role::ORGANIZER));
        DB::table('users')->where('id', $unverified->id)->update(['email_verified_at' => null]);

        $recipients = $this->app->make(TeamOrderNotificationRecipientService::class)->recipientsFor($this->event(), null);

        $this->assertSame([strtolower($subscribed->email)], array_map(fn (UserDomainObject $user) => strtolower($user->getEmail()), $recipients));
    }

    public function test_the_organizer_address_is_not_emailed_twice(): void
    {
        $member = $this->subscribedMember($this->addAccountUser($this->accountId, Role::ORGANIZER));

        $recipients = $this->app->make(TeamOrderNotificationRecipientService::class)->recipientsFor($this->event(), strtoupper($member->email));

        $this->assertSame([], $recipients);
    }

    public function test_owner_is_told_when_someone_else_adds_an_admin(): void
    {
        $admin = $this->addAccountUser($this->accountId, Role::ADMIN);

        $this->postJson('/users', [
            'first_name' => 'New',
            'email' => 'new-admin-'.uniqid().'@test.com',
            'role' => Role::ADMIN->value,
        ], $this->headersFor($admin, $this->accountId))->assertCreated();

        Mail::assertQueued(AdminAccessGrantedMail::class, fn (AdminAccessGrantedMail $mail) => $mail->hasTo($this->owner->email));
    }

    public function test_owner_is_not_emailed_about_admins_they_add_themselves(): void
    {
        $this->postJson('/users', [
            'first_name' => 'New',
            'email' => 'new-admin-'.uniqid().'@test.com',
            'role' => Role::ADMIN->value,
        ], $this->headersFor($this->owner, $this->accountId))->assertCreated();

        Mail::assertNotQueued(AdminAccessGrantedMail::class);
    }

    public function test_owner_is_told_when_a_member_is_promoted_to_admin(): void
    {
        $admin = $this->addAccountUser($this->accountId, Role::ADMIN);
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->putJson("/users/{$member->id}", [
            'first_name' => 'Promoted',
            'last_name' => 'Member',
            'role' => Role::ADMIN->value,
            'status' => 'ACTIVE',
        ], $this->headersFor($admin, $this->accountId))->assertOk();

        Mail::assertQueued(AdminAccessGrantedMail::class, fn (AdminAccessGrantedMail $mail) => $mail->hasTo($this->owner->email));
    }

    public function test_owner_is_told_an_invited_admin_has_only_been_invited(): void
    {
        $admin = $this->addAccountUser($this->accountId, Role::ADMIN);

        $this->postJson('/users', [
            'first_name' => 'New',
            'email' => 'new-admin-'.uniqid().'@test.com',
            'role' => Role::ADMIN->value,
        ], $this->headersFor($admin, $this->accountId))->assertCreated();

        Mail::assertQueued(AdminAccessGrantedMail::class, fn (AdminAccessGrantedMail $mail) => $mail->hasTo($this->owner->email)
            && $mail->content()->with['isInvitation'] === true);
    }

    public function test_promoting_an_invited_member_is_reported_as_an_invitation(): void
    {
        $admin = $this->addAccountUser($this->accountId, Role::ADMIN);
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER, UserStatus::INVITED);

        $this->putJson("/users/{$member->id}", [
            'first_name' => 'Promoted',
            'last_name' => 'Member',
            'role' => Role::ADMIN->value,
        ], $this->headersFor($admin, $this->accountId))->assertOk();

        Mail::assertQueued(AdminAccessGrantedMail::class, fn (AdminAccessGrantedMail $mail) => $mail->content()->with['isInvitation'] === true);
    }

    public function test_promoting_an_active_member_is_reported_as_admin_access(): void
    {
        $admin = $this->addAccountUser($this->accountId, Role::ADMIN);
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->putJson("/users/{$member->id}", [
            'first_name' => 'Promoted',
            'last_name' => 'Member',
            'role' => Role::ADMIN->value,
            'status' => 'ACTIVE',
        ], $this->headersFor($admin, $this->accountId))->assertOk();

        Mail::assertQueued(AdminAccessGrantedMail::class, fn (AdminAccessGrantedMail $mail) => $mail->content()->with['isInvitation'] === false);
    }

    private function subscribedMember(User $user): User
    {
        DB::table('account_users')
            ->where('id', $this->accountUserId($user, $this->accountId))
            ->update(['notify_new_orders' => true]);

        return $user;
    }

    private function event(): EventDomainObject
    {
        return $this->app->make(EventRepositoryInterface::class)->findById($this->eventId);
    }
}
