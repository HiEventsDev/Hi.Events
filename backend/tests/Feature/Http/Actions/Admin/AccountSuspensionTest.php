<?php

namespace Tests\Feature\Http\Actions\Admin;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Mail\Account\AccountSuspendedEmail;
use HiEvents\Models\User;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Stripe\Collection;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;
use Stripe\Subscription;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\UsesApiKeys;
use Tests\TestCase;

class AccountSuspensionTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;
    use UsesApiKeys;

    private const PASSWORD = 'correct-horse-battery-staple';

    private User $superadmin;

    private int $superadminAccountId;

    private User $owner;

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Cache::flush();

        [$this->superadmin, $this->superadminAccountId] = $this->createAccountOwner();
        DB::table('account_users')
            ->where(['account_id' => $this->superadminAccountId, 'user_id' => $this->superadmin->id])
            ->update(['role' => Role::SUPERADMIN->name]);

        $this->owner = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $this->accountId = $this->owner->accounts()->first()->id;
    }

    public function test_only_superadmins_can_suspend_or_unsuspend(): void
    {
        [$admin, $adminAccountId] = $this->createAccountOwner();

        $this->postJson("/admin/accounts/{$this->accountId}/suspension", [], $this->headersFor($admin, $adminAccountId))
            ->assertForbidden();
        $this->deleteJson("/admin/accounts/{$this->accountId}/suspension", [], $this->headersFor($admin, $adminAccountId))
            ->assertForbidden();

        $this->assertNull(DB::table('accounts')->where('id', $this->accountId)->value('suspended_at'));
    }

    public function test_suspending_unpublishes_events_and_organizers(): void
    {
        $organizerId = $this->createOrganizer($this->accountId);
        DB::table('organizers')->where('id', $organizerId)->update(['status' => 'LIVE']);
        $liveEventId = $this->createEventWithStatus($organizerId, 'LIVE');
        $reviewEventId = $this->createEventWithStatus($organizerId, 'PENDING_MANUAL_REVIEW');
        $archivedEventId = $this->createEventWithStatus($organizerId, 'ARCHIVED');

        $this->suspend(['reason' => 'Phishing'])
            ->assertOk()
            ->assertJsonPath('data.suspension_reason', 'Phishing')
            ->assertJsonPath('data.suspended_at', fn ($value) => $value !== null);

        $this->assertSame('DRAFT', DB::table('events')->where('id', $liveEventId)->value('status'));
        $this->assertSame('DRAFT', DB::table('events')->where('id', $reviewEventId)->value('status'));
        $this->assertSame('ARCHIVED', DB::table('events')->where('id', $archivedEventId)->value('status'));
        $this->assertSame('DRAFT', DB::table('organizers')->where('id', $organizerId)->value('status'));
        Mail::assertNothingQueued();
    }

    public function test_the_account_owner_is_emailed_only_when_asked(): void
    {
        $this->suspend(['reason' => 'Phishing', 'notify_account_owner' => true])->assertOk();

        $accountEmail = DB::table('accounts')->where('id', $this->accountId)->value('email');

        Mail::assertQueued(AccountSuspendedEmail::class, fn (AccountSuspendedEmail $mail) => $mail->hasTo($accountEmail));
    }

    public function test_an_account_cannot_be_suspended_twice(): void
    {
        $this->suspend()->assertOk();

        $this->suspend()->assertStatus(409);
    }

    public function test_accounts_with_a_superadmin_member_cannot_be_suspended(): void
    {
        $this->postJson(
            "/admin/accounts/{$this->superadminAccountId}/suspension",
            [],
            $this->headersFor($this->superadmin, $this->superadminAccountId),
        )->assertStatus(409);

        $this->assertNull(DB::table('accounts')->where('id', $this->superadminAccountId)->value('suspended_at'));
    }

    public function test_existing_sessions_are_blocked_on_signed_in_routes(): void
    {
        $this->suspend()->assertOk();

        $this->getJson('/events', $this->headersFor($this->owner, $this->accountId))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'ACCOUNT_SUSPENDED');
        $this->getJson('/users/me', $this->headersFor($this->owner, $this->accountId))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'ACCOUNT_SUSPENDED');
    }

    public function test_public_routes_treat_a_suspended_session_as_anonymous(): void
    {
        $organizerId = $this->createOrganizer($this->accountId);
        $eventId = $this->createEventWithStatus($organizerId, 'LIVE');
        $this->suspend()->assertOk();

        $this->getJson("/public/events/{$eventId}", $this->headersFor($this->owner, $this->accountId))
            ->assertNotFound();
    }

    public function test_api_keys_are_blocked(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        $this->suspend()->assertOk();

        $this->getJson('/organizers', $this->apiKeyHeaders($apiKey))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'ACCOUNT_SUSPENDED');
    }

    public function test_impersonating_admins_can_still_use_the_account(): void
    {
        $this->suspend()->assertOk();

        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
        $token = JWTAuth::claims([
            'account_id' => $this->accountId,
            'is_impersonating' => true,
            'impersonator_id' => $this->superadmin->id,
        ])->fromUser($this->owner);

        $this->getJson('/events', ['Authorization' => 'Bearer '.$token])->assertOk();
    }

    public function test_members_of_a_suspended_account_cannot_sign_in(): void
    {
        $this->suspend()->assertOk();

        $this->postJson('/auth/login', ['email' => $this->owner->email, 'password' => self::PASSWORD])
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'ACCOUNT_SUSPENDED');
    }

    public function test_members_of_several_accounts_sign_in_to_the_one_that_is_not_suspended(): void
    {
        [, $otherAccountId] = $this->createAccountOwner();
        $this->owner->accounts()->attach($otherAccountId, [
            'role' => Role::ADMIN->value,
            'status' => 'ACTIVE',
            'is_account_owner' => false,
        ]);
        $this->suspend()->assertOk();

        $response = $this->postJson('/auth/login', ['email' => $this->owner->email, 'password' => self::PASSWORD])
            ->assertOk();

        $this->assertSame([$otherAccountId], collect($response->json('accounts'))->pluck('id')->all());
        $this->assertNotNull($response->json('token'));
    }

    public function test_unsuspending_restores_access_but_leaves_events_in_draft(): void
    {
        $organizerId = $this->createOrganizer($this->accountId);
        $eventId = $this->createEventWithStatus($organizerId, 'LIVE');
        $this->suspend(['reason' => 'Phishing'])->assertOk();

        $this->deleteJson("/admin/accounts/{$this->accountId}/suspension", [], $this->superadminHeaders())
            ->assertOk()
            ->assertJsonPath('data.suspended_at', null)
            ->assertJsonPath('data.suspension_reason', null);

        $this->getJson('/events', $this->headersFor($this->owner, $this->accountId))->assertOk();
        $this->postJson('/auth/login', ['email' => $this->owner->email, 'password' => self::PASSWORD])->assertOk();
        $this->assertSame('DRAFT', DB::table('events')->where('id', $eventId)->value('status'));
    }

    public function test_unsuspending_an_active_account_conflicts(): void
    {
        $this->deleteJson("/admin/accounts/{$this->accountId}/suspension", [], $this->superadminHeaders())
            ->assertStatus(409);
    }

    public function test_suspending_stops_the_accounts_subscription_from_renewing(): void
    {
        DB::table('account_subscriptions')->insert([
            'account_id' => $this->accountId,
            'stripe_customer_id' => 'cus_suspended',
            'stripe_subscription_id' => 'sub_live',
            'status' => 'ACTIVE',
            'cancel_at_period_end' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stripeSubscriptions = Mockery::mock(SubscriptionService::class);
        $stripeSubscriptions->shouldReceive('all')->andReturn(Collection::constructFrom(['object' => 'list', 'data' => [
            Subscription::constructFrom(['id' => 'sub_live', 'status' => 'active', 'cancel_at_period_end' => false]),
        ]]));
        $stripeSubscriptions->shouldReceive('update')->with('sub_live', ['cancel_at_period_end' => true])->once();
        $stripeClient = Mockery::mock(StripeClient::class);
        $stripeClient->shouldReceive('getService')->with('subscriptions')->andReturn($stripeSubscriptions);
        $stripeClientFactory = Mockery::mock(StripeClientFactory::class);
        $stripeClientFactory->shouldReceive('createForBilling')->andReturn($stripeClient);
        $this->app->instance(StripeClientFactory::class, $stripeClientFactory);

        $this->suspend(['reason' => 'Chargebacks'])->assertOk();

        $this->assertTrue((bool) DB::table('account_subscriptions')->where('account_id', $this->accountId)->value('cancel_at_period_end'));
    }

    private function suspend(array $payload = []): TestResponse
    {
        return $this->postJson("/admin/accounts/{$this->accountId}/suspension", $payload, $this->superadminHeaders());
    }

    private function superadminHeaders(): array
    {
        return $this->headersFor($this->superadmin, $this->superadminAccountId);
    }

    private function createEventWithStatus(int $organizerId, string $status): int
    {
        $eventId = $this->createEvent($this->accountId, $organizerId);
        DB::table('events')->where('id', $eventId)->update(['status' => $status]);

        return $eventId;
    }
}
