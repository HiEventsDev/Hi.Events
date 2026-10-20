<?php

namespace Tests\Feature\Http\Actions\Users;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Mail\Account\AdminAccessGrantedMail;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class UpdateUserActionTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private User $owner;

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
    }

    public static function missingLastNames(): array
    {
        return [
            'empty string' => [['last_name' => '']],
            'null' => [['last_name' => null]],
            'omitted' => [[]],
        ];
    }

    #[DataProvider('missingLastNames')]
    public function test_member_without_a_last_name_can_have_their_role_changed(array $lastName): void
    {
        $member = $this->memberWithoutLastName();

        $this->putJson("/users/{$member->id}", [
            'first_name' => 'Solo',
            'role' => Role::VIEWER->value,
            ...$lastName,
        ], $this->headersFor($this->owner, $this->accountId))->assertOk();

        $this->assertSame(Role::VIEWER->value, $this->accountUserRow($member)->role);
        $this->assertNull(DB::table('users')->where('id', $member->id)->value('last_name'));
    }

    public function test_member_without_a_last_name_can_be_deactivated(): void
    {
        $member = $this->memberWithoutLastName();

        $this->putJson("/users/{$member->id}", [
            'first_name' => 'Solo',
            'last_name' => null,
            'role' => Role::ORGANIZER->value,
            'status' => UserStatus::INACTIVE->name,
        ], $this->headersFor($this->owner, $this->accountId))->assertOk();

        $this->assertSame(UserStatus::INACTIVE->name, $this->accountUserRow($member)->status);
    }

    public function test_superadmin_owner_can_be_renamed_without_sending_a_role(): void
    {
        DB::table('account_users')
            ->where(['account_id' => $this->accountId, 'user_id' => $this->owner->id])
            ->update(['role' => Role::SUPERADMIN->value]);

        $this->putJson("/users/{$this->owner->id}", [
            'first_name' => 'Fixed',
            'last_name' => 'Typo',
        ], $this->headersFor($this->owner, $this->accountId))->assertOk();

        $this->assertSame(Role::SUPERADMIN->value, $this->accountUserRow($this->owner)->role);
        $this->assertSame('Fixed', DB::table('users')->where('id', $this->owner->id)->value('first_name'));
        $this->assertSame('Typo', DB::table('users')->where('id', $this->owner->id)->value('last_name'));
        Mail::assertNotQueued(AdminAccessGrantedMail::class);
    }

    public function test_admin_owner_can_be_renamed_without_sending_a_role(): void
    {
        $admin = $this->addAccountUser($this->accountId, Role::ADMIN);

        $this->putJson("/users/{$this->owner->id}", [
            'first_name' => 'Renamed',
            'last_name' => 'Owner',
        ], $this->headersFor($admin, $this->accountId))->assertOk();

        $this->assertSame(Role::ADMIN->value, $this->accountUserRow($this->owner)->role);
        $this->assertSame('Renamed', DB::table('users')->where('id', $this->owner->id)->value('first_name'));
    }

    public function test_the_super_admin_role_still_cannot_be_assigned(): void
    {
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->putJson("/users/{$member->id}", [
            'first_name' => 'Would',
            'last_name' => 'Escalate',
            'role' => Role::SUPERADMIN->value,
        ], $this->headersFor($this->owner, $this->accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertSame(Role::ORGANIZER->value, $this->accountUserRow($member)->role);
    }

    public function test_omitting_the_role_keeps_the_members_role_and_access(): void
    {
        $organizerId = $this->createOrganizer($this->accountId);
        $member = $this->addRestrictedAccountUser($this->accountId, Role::ORGANIZER, organizerIds: [$organizerId], permissions: ['VIEW_FINANCIALS' => false]);

        $this->putJson("/users/{$member->id}", [
            'first_name' => 'Name',
            'last_name' => 'Only',
        ], $this->headersFor($this->owner, $this->accountId))->assertOk();

        $row = $this->accountUserRow($member);
        $this->assertSame(Role::ORGANIZER->value, $row->role);
        $this->assertSame('RESTRICTED', $row->access_scope);
        $this->assertSame([$organizerId], DB::table('account_user_access_grants')->where('account_user_id', $row->id)->pluck('organizer_id')->all());
        $this->assertFalse((bool) DB::table('account_user_permissions')->where(['account_user_id' => $row->id, 'permission' => 'VIEW_FINANCIALS'])->value('granted'));
    }

    public function test_access_sent_without_a_role_is_applied_to_the_current_role(): void
    {
        $organizerId = $this->createOrganizer($this->accountId);
        $eventId = $this->createEvent($this->accountId, $organizerId);
        $member = $this->addAccountUser($this->accountId, Role::VIEWER);

        $this->putJson("/users/{$member->id}", [
            'first_name' => 'Scoped',
            'last_name' => 'Viewer',
            'access_scope' => 'RESTRICTED',
            'event_ids' => [$eventId],
        ], $this->headersFor($this->owner, $this->accountId))->assertOk();

        $row = $this->accountUserRow($member);
        $this->assertSame(Role::VIEWER->value, $row->role);
        $this->assertSame('RESTRICTED', $row->access_scope);
        $this->assertSame([$eventId], DB::table('account_user_access_grants')->where('account_user_id', $row->id)->pluck('event_id')->all());
    }

    private function memberWithoutLastName(): User
    {
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        DB::table('users')->where('id', $member->id)->update(['first_name' => 'Solo', 'last_name' => null]);

        return $member;
    }

    private function accountUserRow(User $user): object
    {
        return DB::table('account_users')
            ->where(['account_id' => $this->accountId, 'user_id' => $user->id])
            ->first();
    }
}
