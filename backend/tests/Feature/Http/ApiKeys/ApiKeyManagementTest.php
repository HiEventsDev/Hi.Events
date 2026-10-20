<?php

namespace Tests\Feature\Http\ApiKeys;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Mail\User\ApiKeyCreatedMail;
use HiEvents\Mail\User\ApiKeyRevokedMail;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Auth\ApiKey\ApiKeyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\UsesApiKeys;
use Tests\TestCase;

class ApiKeyManagementTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;
    use UsesApiKeys;

    private const PASSWORD = 'correct-horse-battery';

    private User $owner;

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->owner = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $this->accountId = $this->owner->accounts()->first()->id;
    }

    public function test_member_creates_a_key_and_sees_it_once(): void
    {
        $response = $this->postJson('/api-keys', $this->payload(), $this->headersFor($this->owner, $this->accountId))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Zapier production')
            ->assertJsonPath('data.access', ApiKeyAccess::READ_WRITE->value)
            ->assertJsonPath('data.is_own', true)
            ->assertJsonPath('data.is_expired', false)
            ->assertJsonPath('data.last_used_at', null)
            ->assertJsonPath('data.owner.id', $this->owner->id);

        $key = $response->json('data.key');
        $this->assertStringStartsWith('hie_', $key);
        $this->assertSame(substr($key, -4), $response->json('data.token_hint'));

        $row = DB::table('personal_access_tokens')->where('id', $response->json('data.id'))->first();
        $this->assertSame(hash('sha256', $key), $row->token);
        $this->assertSame($this->accountId, $row->account_id);
        $this->assertSame($this->owner->id, $row->tokenable_id);
        $this->assertSame(User::class, $row->tokenable_type);
        $this->assertSame(['read', 'write'], json_decode($row->abilities, true));
        $this->assertTrue(CarbonImmutable::parse($row->expires_at)->isSameDay(now()->addDays(90)));

        Mail::assertQueued(ApiKeyCreatedMail::class, fn (ApiKeyCreatedMail $mail) => $mail->hasTo($this->owner->email));

        $this->getJson('/api-keys', $this->headersFor($this->owner, $this->accountId))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.key');
    }

    public function test_key_without_expiry_never_expires(): void
    {
        $response = $this->postJson('/api-keys', $this->payload(['expires_in_days' => null]), $this->headersFor($this->owner, $this->accountId))
            ->assertCreated()
            ->assertJsonPath('data.expires_at', null);

        $this->assertNull(DB::table('personal_access_tokens')->where('id', $response->json('data.id'))->value('expires_at'));
    }

    public function test_creating_a_key_needs_the_password(): void
    {
        $this->postJson('/api-keys', $this->payload(['password' => 'wrong-password']), $this->headersFor($this->owner, $this->accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->postJson('/api-keys', $this->payload(['password' => null]), $this->headersFor($this->owner, $this->accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        Mail::assertNothingQueued();
    }

    public function test_validation_rejects_bad_input(): void
    {
        $headers = $this->headersFor($this->owner, $this->accountId);

        $this->postJson('/api-keys', $this->payload(['name' => '[Click here](https://example.com)']), $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson('/api-keys', $this->payload(['name' => str_repeat('a', 61)]), $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson('/api-keys', $this->payload(['expires_in_days' => 13]), $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expires_in_days');

        $this->postJson('/api-keys', $this->payload(['access' => 'ADMIN']), $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_keys_cannot_be_created_while_impersonating(): void
    {
        [$admin] = $this->createAccountOwner();
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        $token = JWTAuth::claims([
            'account_id' => $this->accountId,
            'impersonator_id' => $admin->id,
            'is_impersonating' => true,
        ])->fromUser($this->owner);

        $this->postJson('/api-keys', $this->payload(), ['Authorization' => 'Bearer '.$token])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'IMPERSONATION_NOT_ALLOWED');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unverified_users_cannot_create_keys(): void
    {
        $this->owner->forceFill(['email_verified_at' => null])->save();

        $this->postJson('/api-keys', $this->payload(), $this->headersFor($this->owner, $this->accountId))
            ->assertConflict();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_viewers_can_only_create_read_only_keys(): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        $viewer->forceFill(['password' => bcrypt(self::PASSWORD)])->save();

        $this->postJson('/api-keys', $this->payload(), $this->headersFor($viewer, $this->accountId))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');

        $this->postJson('/api-keys', $this->payload(['access' => ApiKeyAccess::READ_ONLY->value]), $this->headersFor($viewer, $this->accountId))
            ->assertCreated()
            ->assertJsonPath('data.access', ApiKeyAccess::READ_ONLY->value);
    }

    public function test_members_have_an_active_key_limit(): void
    {
        for ($i = 0; $i < ApiKeyService::MAX_ACTIVE_KEYS_PER_MEMBER; $i++) {
            $this->createApiKey($this->owner, $this->accountId);
        }

        $this->postJson('/api-keys', $this->payload(), $this->headersFor($this->owner, $this->accountId))
            ->assertConflict();

        DB::table('personal_access_tokens')->limit(1)->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api-keys', $this->payload(), $this->headersFor($this->owner, $this->accountId))
            ->assertCreated();
    }

    public function test_members_only_see_their_own_keys_and_admins_see_everyone(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->createApiKey($this->owner, $this->accountId, name: 'Owner key');
        $this->createApiKey($organizer, $this->accountId, name: 'Organizer key');

        [$otherOwner, $otherAccountId] = $this->createAccountOwner();
        $this->createApiKey($otherOwner, $otherAccountId, name: 'Other account key');

        $this->getJson('/api-keys', $this->headersFor($organizer, $this->accountId))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Organizer key')
            ->assertJsonPath('data.0.is_own', true);

        $this->getJson('/api-keys', $this->headersFor($this->owner, $this->accountId))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Organizer key')
            ->assertJsonPath('data.0.is_own', false)
            ->assertJsonPath('data.0.owner.email', $organizer->email)
            ->assertJsonPath('data.1.name', 'Owner key')
            ->assertJsonPath('data.1.is_own', true);
    }

    public function test_members_revoke_their_own_keys(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->createApiKey($organizer, $this->accountId);
        $keyId = DB::table('personal_access_tokens')->value('id');

        $this->deleteJson("/api-keys/$keyId", [], $this->headersFor($organizer, $this->accountId))->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $keyId]);
        Mail::assertNotQueued(ApiKeyRevokedMail::class);
    }

    public function test_members_cannot_revoke_someone_elses_key(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->createApiKey($this->owner, $this->accountId);
        $keyId = DB::table('personal_access_tokens')->value('id');

        $this->deleteJson("/api-keys/$keyId", [], $this->headersFor($organizer, $this->accountId))->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $keyId]);
    }

    public function test_admins_revoke_a_members_key_and_the_member_is_told(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->createApiKey($organizer, $this->accountId, name: 'Forgotten integration');
        $keyId = DB::table('personal_access_tokens')->value('id');

        $this->deleteJson("/api-keys/$keyId", [], $this->headersFor($this->owner, $this->accountId))->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $keyId]);
        Mail::assertQueued(ApiKeyRevokedMail::class, fn (ApiKeyRevokedMail $mail) => $mail->hasTo($organizer->email));
    }

    public function test_keys_from_another_account_are_not_found(): void
    {
        [$otherOwner, $otherAccountId] = $this->createAccountOwner();
        $this->createApiKey($otherOwner, $otherAccountId);
        $keyId = DB::table('personal_access_tokens')->value('id');

        $this->deleteJson("/api-keys/$keyId", [], $this->headersFor($this->owner, $this->accountId))->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $keyId]);
    }

    public function test_api_keys_cannot_manage_api_keys(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $this->getJson('/api-keys', $this->apiKeyHeaders($apiKey))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'API_KEY_NOT_ALLOWED');

        $this->postJson('/api-keys', $this->payload(), $this->apiKeyHeaders($apiKey))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'API_KEY_NOT_ALLOWED');
    }

    public function test_deactivating_a_member_revokes_their_keys(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->createApiKey($organizer, $this->accountId);

        $this->putJson("/users/$organizer->id", [
            'first_name' => 'Gone',
            'last_name' => 'Member',
            'role' => Role::ORGANIZER->value,
            'status' => 'INACTIVE',
        ], $this->headersFor($this->owner, $this->accountId))->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $organizer->id]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Zapier production',
            'access' => ApiKeyAccess::READ_WRITE->value,
            'expires_in_days' => 90,
            'password' => self::PASSWORD,
            ...$overrides,
        ];
    }
}
