<?php

namespace Tests\Feature\Http\ApiKeys;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Auth\UserSessionRevocationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\UsesApiKeys;
use Tests\TestCase;

class ApiKeyAuthenticationTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;
    use UsesApiKeys;

    private User $owner;

    private int $accountId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId);
    }

    public function test_a_key_authenticates_as_its_owner_in_its_account(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $this->getJson('/users/me', $this->apiKeyHeaders($apiKey))
            ->assertOk()
            ->assertJsonPath('data.id', $this->owner->id)
            ->assertJsonPath('data.account_id', $this->accountId);

        $this->getJson('/organizers', $this->apiKeyHeaders($apiKey))
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->organizerId);

        $this->assertNotNull(DB::table('personal_access_tokens')->value('last_used_at'));
    }

    public function test_last_used_is_recorded_at_most_every_few_minutes(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        $recentlyUsed = now()->subMinute()->startOfSecond();
        DB::table('personal_access_tokens')->update(['last_used_at' => $recentlyUsed]);

        $this->getJson('/users/me', $this->apiKeyHeaders($apiKey))->assertOk();
        $this->assertEquals($recentlyUsed, Carbon::parse(DB::table('personal_access_tokens')->value('last_used_at')));

        DB::table('personal_access_tokens')->update(['last_used_at' => now()->subMinutes(10)]);
        $this->getJson('/users/me', $this->apiKeyHeaders($apiKey))->assertOk();
        $this->assertTrue(Carbon::parse(DB::table('personal_access_tokens')->value('last_used_at'))->gt(now()->subMinute()));
    }

    public function test_the_numeric_id_prefix_form_is_accepted(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        $id = DB::table('personal_access_tokens')->value('id');

        $this->getJson('/organizers', $this->apiKeyHeaders($id.'|'.$apiKey))->assertOk();
    }

    public function test_read_only_keys_cannot_write(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId, ApiKeyAccess::READ_ONLY);

        $this->getJson("/organizers/$this->organizerId", $this->apiKeyHeaders($apiKey))->assertOk();

        $this->postJson('/organizers', $this->organizerPayload(), $this->apiKeyHeaders($apiKey))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'API_KEY_READ_ONLY');

        $this->assertDatabaseMissing('organizers', ['name' => 'Created over the API']);
    }

    public function test_read_only_keys_can_run_exports(): void
    {
        $eventId = $this->createEvent($this->accountId, $this->organizerId);
        $apiKey = $this->createApiKey($this->owner, $this->accountId, ApiKeyAccess::READ_ONLY);

        $this->postJson("/events/$eventId/attendees/export", [], $this->apiKeyHeaders($apiKey))
            ->assertOk()
            ->assertDownload('attendees.xlsx');

        $this->postJson("/events/$eventId/products/sort", [], $this->apiKeyHeaders($apiKey))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'API_KEY_READ_ONLY');
    }

    public function test_read_write_keys_can_write(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $this->postJson('/organizers', $this->organizerPayload(), $this->apiKeyHeaders($apiKey))->assertCreated();

        $this->assertDatabaseHas('organizers', ['name' => 'Created over the API', 'account_id' => $this->accountId]);
    }

    public function test_a_key_never_exceeds_its_owners_role(): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        $apiKey = $this->createApiKey($viewer, $this->accountId);

        $this->postJson('/organizers', $this->organizerPayload(), $this->apiKeyHeaders($apiKey))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'READ_ONLY_ROLE');
    }

    public function test_a_restricted_members_key_only_reaches_their_grants(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Not granted');
        $member = $this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->organizerId]);
        $apiKey = $this->createApiKey($member, $this->accountId);

        $this->getJson("/organizers/$this->organizerId", $this->apiKeyHeaders($apiKey))->assertOk();
        $this->getJson("/organizers/$otherOrganizerId", $this->apiKeyHeaders($apiKey))->assertForbidden();
    }

    public function test_a_key_is_scoped_to_its_account(): void
    {
        [$otherOwner, $otherAccountId] = $this->createAccountOwner();
        $otherOrganizerId = $this->createOrganizer($otherAccountId);
        $this->owner->accounts()->attach($otherAccountId, [
            'role' => Role::ADMIN->value,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => false,
        ]);

        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $this->getJson('/organizers', $this->apiKeyHeaders($apiKey))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->organizerId);

        $this->getJson("/organizers/$otherOrganizerId", $this->apiKeyHeaders($apiKey))->assertForbidden();
    }

    public function test_expired_keys_are_rejected(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        DB::table('personal_access_tokens')->update(['expires_at' => now()->subMinute()]);

        $this->getJson('/organizers', $this->apiKeyHeaders($apiKey))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'This API key is invalid, has expired or has been revoked.');
    }

    public function test_revoked_keys_are_rejected(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        DB::table('personal_access_tokens')->delete();

        $this->getJson('/organizers', $this->apiKeyHeaders($apiKey))->assertUnauthorized();
    }

    public function test_keys_stop_working_when_the_member_is_deactivated_or_removed(): void
    {
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $apiKey = $this->createApiKey($member, $this->accountId);

        DB::table('account_users')->where(['user_id' => $member->id, 'account_id' => $this->accountId])->update(['status' => UserStatus::INACTIVE->name]);
        $this->getJson('/organizers', $this->apiKeyHeaders($apiKey))->assertUnauthorized();

        DB::table('account_users')->where(['user_id' => $member->id, 'account_id' => $this->accountId])->delete();
        $this->getJson('/organizers', $this->apiKeyHeaders($apiKey))->assertUnauthorized();
    }

    public function test_keys_stop_working_when_the_user_is_deleted(): void
    {
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $apiKey = $this->createApiKey($member, $this->accountId);

        $member->delete();

        $this->getJson('/organizers', $this->apiKeyHeaders($apiKey))->assertUnauthorized();
    }

    public function test_revoking_a_users_sessions_also_revokes_their_keys(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        [$otherOwner, $otherAccountId] = $this->createAccountOwner();
        $otherApiKey = $this->createApiKey($otherOwner, $otherAccountId);

        app(UserSessionRevocationService::class)->revokeAll($this->owner->id);

        $this->getJson('/organizers', $this->apiKeyHeaders($apiKey))->assertUnauthorized();
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $this->owner->id]);
        $this->getJson('/organizers', $this->apiKeyHeaders($otherApiKey))->assertOk();
    }

    public function test_a_password_reset_revokes_keys(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        $token = 'reset-token-'.uniqid();
        DB::table('password_reset_tokens')->insert([
            'email' => $this->owner->email,
            'token' => $token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/auth/reset-password/$token", [
            'password' => 'a-brand-new-password-123',
            'password_confirmation' => 'a-brand-new-password-123',
        ])->assertSuccessful();

        $this->getJson('/organizers', $this->apiKeyHeaders($apiKey))->assertUnauthorized();
    }

    public function test_session_only_routes_refuse_keys(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        foreach ([
            ['PUT', '/users/me'],
            ['GET', '/users'],
            ['GET', '/users/me/two-factor'],
            ['POST', '/accounts/deletion-request'],
            ['PUT', "/accounts/$this->accountId"],
            ['POST', "/organizers/$this->organizerId/stripe/connect"],
            ['GET', '/auth/logout'],
        ] as [$method, $uri]) {
            $this->json($method, $uri, [], $this->apiKeyHeaders($apiKey))
                ->assertForbidden()
                ->assertJsonPath('error_code', 'API_KEY_NOT_ALLOWED');
        }
    }

    public function test_account_two_factor_requirement_applies_to_keys(): void
    {
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $apiKey = $this->createApiKey($member, $this->accountId);
        DB::table('accounts')->where('id', $this->accountId)->update(['require_two_factor_authentication' => true]);

        $this->getJson('/organizers', $this->apiKeyHeaders($apiKey))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'TWO_FACTOR_SETUP_REQUIRED');
    }

    public function test_keys_are_blocked_while_the_account_is_pending_deletion(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        DB::table('account_deletion_requests')->insert([
            'account_id' => $this->accountId,
            'requested_by_user_id' => $this->owner->id,
            'initiated_by' => 'USER',
            'status' => 'REQUESTED',
            'expected_outcome' => 'HARD_DELETE',
            'scheduled_deletion_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::flush();

        $this->getJson('/organizers', $this->apiKeyHeaders($apiKey))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'ACCOUNT_PENDING_DELETION');
    }

    public function test_keys_on_public_routes_are_ignored(): void
    {
        $this->getJson('/public/instance', $this->apiKeyHeaders('hie_not-a-real-key'))->assertOk();
    }

    public function test_a_session_token_still_works_on_every_route_group(): void
    {
        $this->getJson('/organizers', $this->headersFor($this->owner, $this->accountId))->assertOk();
        $this->getJson('/users', $this->headersFor($this->owner, $this->accountId))->assertOk();
    }

    private function organizerPayload(): array
    {
        return [
            'name' => 'Created over the API',
            'email' => 'api@example.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ];
    }
}
