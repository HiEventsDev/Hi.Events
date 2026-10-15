<?php

namespace Tests\Concerns;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Models\User;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

trait ActsAsAccountUsers
{
    /**
     * @return array{0: User, 1: int}
     */
    protected function createAccountOwner(): array
    {
        $user = User::factory()->withAccount()->create();

        return [$user, $user->accounts()->first()->id];
    }

    protected function addAccountUser(int $accountId, Role $role = Role::ORGANIZER, UserStatus $status = UserStatus::ACTIVE): User
    {
        $user = User::factory()->create();

        $user->accounts()->attach($accountId, [
            'role' => $role->value,
            'status' => $status->name,
            'is_account_owner' => false,
        ]);

        return $user;
    }

    protected function tokenFor(User $user, int $accountId): string
    {
        return JWTAuth::claims(['account_id' => $accountId])->fromUser($user);
    }

    protected function headersFor(User $user, int $accountId): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return ['Authorization' => 'Bearer '.$this->tokenFor($user, $accountId)];
    }

    protected function createOrganizer(int $accountId, string $name = 'Test Organizer'): int
    {
        return DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => $name,
            'email' => 'organizer-'.uniqid().'@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  int[]  $organizerIds
     * @param  int[]  $eventIds
     * @param  array<string, bool>  $permissions
     */
    protected function addRestrictedAccountUser(
        int $accountId,
        Role $role = Role::ORGANIZER,
        array $organizerIds = [],
        array $eventIds = [],
        array $permissions = [],
    ): User {
        $user = $this->addAccountUser($accountId, $role);
        $accountUserId = $this->accountUserId($user, $accountId);

        DB::table('account_users')->where('id', $accountUserId)->update(['access_scope' => 'RESTRICTED']);

        foreach ($organizerIds as $organizerId) {
            DB::table('account_user_access_grants')->insert([
                'account_user_id' => $accountUserId,
                'organizer_id' => $organizerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($eventIds as $eventId) {
            DB::table('account_user_access_grants')->insert([
                'account_user_id' => $accountUserId,
                'event_id' => $eventId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->setPermissionOverrides($user, $accountId, $permissions);

        return $user;
    }

    /**
     * @param  array<string, bool>  $permissions
     */
    protected function setPermissionOverrides(User $user, int $accountId, array $permissions): void
    {
        foreach ($permissions as $permission => $granted) {
            DB::table('account_user_permissions')->insert([
                'account_user_id' => $this->accountUserId($user, $accountId),
                'permission' => $permission,
                'granted' => $granted,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function accountUserId(User $user, int $accountId): int
    {
        return DB::table('account_users')
            ->where(['account_id' => $accountId, 'user_id' => $user->id])
            ->value('id');
    }

    protected function createEvent(int $accountId, int $organizerId, string $title = 'Test Event'): int
    {
        return DB::table('events')->insertGetId([
            'title' => $title,
            'account_id' => $accountId,
            'user_id' => DB::table('account_users')->where('account_id', $accountId)->value('user_id'),
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'ev_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
