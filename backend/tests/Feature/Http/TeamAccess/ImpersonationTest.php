<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public static function restrictedTargets(): array
    {
        return [
            'viewer' => [Role::VIEWER, UserStatus::ACTIVE],
            'inactive organizer' => [Role::ORGANIZER, UserStatus::INACTIVE],
        ];
    }

    #[DataProvider('restrictedTargets')]
    public function test_a_superadmin_can_always_stop_impersonating(Role $role, UserStatus $status): void
    {
        [$superadmin, $superadminAccountId] = $this->createAccountOwner();
        DB::table('account_users')->where(['user_id' => $superadmin->id, 'account_id' => $superadminAccountId])->update(['role' => Role::SUPERADMIN->value]);
        [, $targetAccountId] = $this->createAccountOwner();
        $target = $this->addAccountUser($targetAccountId, $role, $status);

        $this->postJson('/admin/stop-impersonation', [], $this->impersonationHeaders($target, $targetAccountId, $superadmin))
            ->assertOk()
            ->assertJsonPath('message', 'Impersonation ended');
    }

    private function impersonationHeaders(User $target, int $accountId, User $impersonator): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        $token = JWTAuth::claims([
            'account_id' => $accountId,
            'impersonator_id' => $impersonator->id,
            'is_impersonating' => true,
        ])->fromUser($target);

        return ['Authorization' => 'Bearer '.$token];
    }
}
