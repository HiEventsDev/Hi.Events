<?php

namespace Tests\Feature\Http\Actions\Admin\Users;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\CreatesSsoConnections;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\TestCase;

class ImpersonationTokenTest extends TestCase
{
    use ActsAsAccountUsers;
    use CreatesSsoConnections;
    use DatabaseTransactions;
    use ManagesFeatureFlags;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);
    }

    public function test_only_a_superadmin_can_start_impersonating(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $target = $this->addAccountUser($accountId);

        $this->postJson("/admin/impersonate/{$target->id}", ['account_id' => $accountId], $this->headersFor($owner, $accountId))
            ->assertForbidden();
    }

    public function test_a_superadmin_cannot_impersonate_another_superadmin(): void
    {
        [$superadmin, $superadminAccountId] = $this->superadmin();
        [, $targetAccountId] = $this->createAccountOwner();
        $target = $this->addAccountUser($targetAccountId, Role::SUPERADMIN);

        $this->postJson("/admin/impersonate/{$target->id}", ['account_id' => $targetAccountId], $this->headersFor($superadmin, $superadminAccountId))
            ->assertForbidden();
    }

    public function test_a_superadmin_cannot_impersonate_someone_outside_the_account(): void
    {
        [$superadmin, $superadminAccountId] = $this->superadmin();
        [, $targetAccountId] = $this->createAccountOwner();
        [$outsider] = $this->createAccountOwner();

        $this->postJson("/admin/impersonate/{$outsider->id}", ['account_id' => $targetAccountId], $this->headersFor($superadmin, $superadminAccountId))
            ->assertForbidden();
    }

    public function test_the_impersonation_token_carries_its_claims_through_a_refresh(): void
    {
        [$superadmin, $superadminAccountId] = $this->superadmin();
        [, $targetAccountId] = $this->createAccountOwner();
        $target = $this->addAccountUser($targetAccountId);

        $token = $this->postJson("/admin/impersonate/{$target->id}", ['account_id' => $targetAccountId], $this->headersFor($superadmin, $superadminAccountId))
            ->assertOk()
            ->json('token');

        $claims = $this->decodeToken($token);
        $this->assertSame($target->id, (int) $claims['sub']);
        $this->assertSame($targetAccountId, $claims['account_id']);
        $this->assertSame('IMPERSONATION', $claims['auth_method']);
        $this->assertSame($superadmin->id, $claims['impersonator_id']);
        $this->assertTrue($claims['is_impersonating']);

        $this->travel(2)->seconds();
        $refreshed = $this->postJson('/auth/refresh', [], $this->bearer($token))->assertOk()->json('token');

        $refreshedClaims = $this->decodeToken($refreshed);
        $this->assertSame($targetAccountId, $refreshedClaims['account_id']);
        $this->assertSame('IMPERSONATION', $refreshedClaims['auth_method']);
        $this->assertSame($superadmin->id, $refreshedClaims['impersonator_id']);
        $this->assertTrue($refreshedClaims['is_impersonating']);
        $this->assertSame($claims['iat'], $refreshedClaims['iat']);
    }

    public function test_stopping_impersonation_retires_the_impersonation_token(): void
    {
        [$superadmin, $superadminAccountId] = $this->superadmin();
        [, $targetAccountId] = $this->createAccountOwner();
        $target = $this->addAccountUser($targetAccountId);

        $impersonationToken = $this->postJson("/admin/impersonate/{$target->id}", ['account_id' => $targetAccountId], $this->headersFor($superadmin, $superadminAccountId))
            ->assertOk()
            ->json('token');

        $returnedToken = $this->postJson('/admin/stop-impersonation', [], $this->bearer($impersonationToken))
            ->assertOk()
            ->json('token');

        $this->getJson('/users/me', $this->bearer($impersonationToken))->assertUnauthorized();
        $this->postJson('/admin/stop-impersonation', [], $this->bearer($impersonationToken))->assertUnauthorized();
        $this->getJson('/users/me', $this->bearer($returnedToken))
            ->assertOk()
            ->assertJsonPath('data.id', $superadmin->id);
    }

    public function test_an_impersonation_token_works_for_a_member_who_must_use_single_sign_on(): void
    {
        $this->setFeatureFlagDefault(FeatureFlag::ENTERPRISE_SSO, true);
        $this->fakeConnectionIssuer();
        [$superadmin, $superadminAccountId] = $this->superadmin();
        [, $targetAccountId] = $this->createAccountOwner();
        $this->createConnection($targetAccountId, ['enforce_sso' => true]);
        $this->addDomain($targetAccountId, 'acme.test');
        $member = User::factory()->create(['email' => 'grace@acme.test']);
        $member->accounts()->attach($targetAccountId, [
            'role' => Role::ORGANIZER->value,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => false,
            'sso_enforced' => true,
        ]);

        $this->getJson('/users/me', $this->headersFor($member, $targetAccountId))
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SSO_REQUIRED');

        $token = $this->postJson("/admin/impersonate/{$member->id}", ['account_id' => $targetAccountId], $this->headersFor($superadmin, $superadminAccountId))
            ->assertOk()
            ->json('token');

        $this->getJson('/users/me', $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.id', $member->id);
    }

    /**
     * @return array{0: User, 1: int}
     */
    private function superadmin(): array
    {
        [$superadmin, $accountId] = $this->createAccountOwner();
        DB::table('account_users')
            ->where(['user_id' => $superadmin->id, 'account_id' => $accountId])
            ->update(['role' => Role::SUPERADMIN->value]);

        return [$superadmin, $accountId];
    }
}
