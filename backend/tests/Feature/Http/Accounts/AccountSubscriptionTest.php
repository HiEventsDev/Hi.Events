<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Accounts;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\AccountSubscriptionStatus;
use HiEvents\DomainObjects\Status\OrganizerStatus;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Stripe\Collection;
use Stripe\Exception\ApiConnectionException;
use Stripe\Price;
use Stripe\Service\PriceService;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;
use Stripe\Subscription;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class AccountSubscriptionTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private SubscriptionService|MockInterface $stripeSubscriptions;

    private PriceService|MockInterface $stripePrices;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripeSubscriptions = Mockery::mock(SubscriptionService::class);
        $stripeClient = Mockery::mock(StripeClient::class);
        $stripeClient->shouldReceive('getService')->with('subscriptions')->andReturn($this->stripeSubscriptions);
        $this->stripePrices = Mockery::mock(PriceService::class);
        $this->stripePrices->shouldReceive('all')->byDefault()->andReturn(Collection::constructFrom(['object' => 'list', 'data' => [
            Price::constructFrom([
                'id' => 'price_yearly',
                'lookup_key' => 'hi_events_pro_yearly',
                'unit_amount' => 29999,
                'currency' => 'eur',
                'currency_options' => ['eur' => ['unit_amount' => 29999], 'usd' => ['unit_amount' => 33599]],
            ]),
            Price::constructFrom([
                'id' => 'price_monthly',
                'lookup_key' => 'hi_events_pro_monthly',
                'unit_amount' => 2999,
                'currency' => 'eur',
                'currency_options' => ['eur' => ['unit_amount' => 2999], 'usd' => ['unit_amount' => 3399]],
            ]),
        ]]));
        $stripeClient->shouldReceive('getService')->with('prices')->andReturn($this->stripePrices);
        $stripeClientFactory = Mockery::mock(StripeClientFactory::class);
        $stripeClientFactory->shouldReceive('createForBilling')->andReturn($stripeClient);
        $this->app->instance(StripeClientFactory::class, $stripeClientFactory);
    }

    public function test_subscriptions_are_unavailable_outside_hi_events(): void
    {
        config(['app.is_hi_events' => false]);
        [$owner, $accountId] = $this->createAccountOwner();

        $this->getJson("/accounts/$accountId/subscription", $this->headersFor($owner, $accountId))
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.active', false);

        $this->postJson("/accounts/$accountId/subscription/checkout-session", ['interval' => 'month'], $this->headersFor($owner, $accountId))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'FEATURE_UNAVAILABLE');

        $this->postJson("/accounts/$accountId/subscription/portal-session", [], $this->headersFor($owner, $accountId))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'FEATURE_UNAVAILABLE');
    }

    public function test_owner_sees_the_subscription(): void
    {
        config(['app.is_hi_events' => true]);
        [$owner, $accountId] = $this->createAccountOwner();
        DB::table('accounts')->where('id', $accountId)->update(['currency_code' => 'USD']);
        $this->givenSubscription($accountId, AccountSubscriptionStatus::PAST_DUE, cancelAtPeriodEnd: true);
        $this->stripeSubscriptions->shouldReceive('all')->andThrow(new ApiConnectionException('Stripe is unreachable'));

        $this->getJson("/accounts/$accountId/subscription?refresh=1", $this->headersFor($owner, $accountId))
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.status', 'PAST_DUE')
            ->assertJsonPath('data.cancel_at_period_end', true)
            ->assertJsonPath('data.prices', [
                ['interval' => 'month', 'unit_amount' => 3399, 'currency' => 'USD'],
                ['interval' => 'year', 'unit_amount' => 33599, 'currency' => 'USD'],
            ]);
    }

    public function test_billing_page_picks_up_a_subscription_the_webhook_never_delivered(): void
    {
        config(['app.is_hi_events' => true]);
        [$owner, $accountId] = $this->createAccountOwner();
        DB::table('account_subscriptions')->insert([
            'account_id' => $accountId,
            'stripe_customer_id' => 'cus_waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->stripeSubscriptions->shouldReceive('all')
            ->with(['customer' => 'cus_waiting', 'status' => 'all', 'limit' => 10])
            ->andReturn(Collection::constructFrom(['object' => 'list', 'data' => [
                Subscription::constructFrom([
                    'id' => 'sub_paid',
                    'customer' => 'cus_waiting',
                    'status' => 'active',
                    'cancel_at_period_end' => false,
                    'items' => ['object' => 'list', 'data' => [[
                        'id' => 'si_1',
                        'current_period_end' => now()->addYear()->getTimestamp(),
                    ]]],
                ]),
            ]]));

        $this->getJson("/accounts/$accountId/subscription?refresh=1", $this->headersFor($owner, $accountId))
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertDatabaseHas('account_subscriptions', [
            'account_id' => $accountId,
            'stripe_subscription_id' => 'sub_paid',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_reading_the_subscription_does_not_call_stripe_unless_asked(): void
    {
        config(['app.is_hi_events' => true]);
        [$owner, $accountId] = $this->createAccountOwner();
        $this->givenSubscription($accountId, AccountSubscriptionStatus::ACTIVE);

        $this->stripeSubscriptions->shouldNotReceive('all');

        $this->getJson("/accounts/$accountId/subscription", $this->headersFor($owner, $accountId))
            ->assertOk()
            ->assertJsonPath('data.active', true);
    }

    public function test_subscriptions_are_unavailable_until_stripe_has_prices(): void
    {
        config(['app.is_hi_events' => true]);
        [$owner, $accountId] = $this->createAccountOwner();
        $this->stripePrices->shouldReceive('all')->andReturn(Collection::constructFrom(['object' => 'list', 'data' => []]));

        $this->getJson("/accounts/$accountId/subscription", $this->headersFor($owner, $accountId))
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_a_subscriber_still_sees_their_subscription_when_stripe_prices_are_missing(): void
    {
        config(['app.is_hi_events' => true]);
        [$owner, $accountId] = $this->createAccountOwner();
        $this->givenSubscription($accountId, AccountSubscriptionStatus::ACTIVE);
        $this->stripePrices->shouldReceive('all')->andReturn(Collection::constructFrom(['object' => 'list', 'data' => []]));

        $this->getJson("/accounts/$accountId/subscription", $this->headersFor($owner, $accountId))
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.prices', []);
    }

    public function test_members_without_manage_account_can_see_but_not_change_billing(): void
    {
        config(['app.is_hi_events' => true]);
        [, $accountId] = $this->createAccountOwner();
        $organizer = $this->addAccountUser($accountId, Role::ORGANIZER);

        $this->getJson("/accounts/$accountId/subscription", $this->headersFor($organizer, $accountId))
            ->assertOk()
            ->assertJsonPath('data.available', true);

        $this->postJson("/accounts/$accountId/subscription/checkout-session", ['interval' => 'month'], $this->headersFor($organizer, $accountId))
            ->assertForbidden();

        $this->postJson("/accounts/$accountId/subscription/portal-session", [], $this->headersFor($organizer, $accountId))
            ->assertForbidden();
    }

    public function test_cannot_read_another_accounts_subscription(): void
    {
        config(['app.is_hi_events' => true]);
        [$owner, $accountId] = $this->createAccountOwner();
        [, $otherAccountId] = $this->createAccountOwner();

        $this->getJson("/accounts/$otherAccountId/subscription", $this->headersFor($owner, $accountId))
            ->assertForbidden();
    }

    public function test_checkout_rejects_an_unknown_interval(): void
    {
        config(['app.is_hi_events' => true]);
        [$owner, $accountId] = $this->createAccountOwner();

        $this->postJson("/accounts/$accountId/subscription/checkout-session", ['interval' => 'week'], $this->headersFor($owner, $accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('interval');
    }

    public function test_checkout_refuses_a_second_subscription(): void
    {
        config(['app.is_hi_events' => true]);
        [$owner, $accountId] = $this->createAccountOwner();
        $this->givenSubscription($accountId, AccountSubscriptionStatus::ACTIVE);
        $this->stripeSubscriptions->shouldReceive('all')->andReturn(Collection::constructFrom(['object' => 'list', 'data' => [
            Subscription::constructFrom(['id' => 'sub_live', 'status' => 'active', 'cancel_at_period_end' => false, 'items' => ['object' => 'list', 'data' => []]]),
        ]]));

        $this->postJson("/accounts/$accountId/subscription/checkout-session", ['interval' => 'year'], $this->headersFor($owner, $accountId))
            ->assertStatus(409);
    }

    public function test_portal_needs_a_subscription(): void
    {
        config(['app.is_hi_events' => true]);
        [$owner, $accountId] = $this->createAccountOwner();

        $this->postJson("/accounts/$accountId/subscription/portal-session", [], $this->headersFor($owner, $accountId))
            ->assertNotFound();
    }

    public function test_public_organizer_reports_whether_branding_is_removed(): void
    {
        config(['app.is_hi_events' => true]);
        [, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        DB::table('organizers')->where('id', $organizerId)->update(['status' => OrganizerStatus::LIVE->name]);

        $this->getJson("/public/organizers/$organizerId")
            ->assertOk()
            ->assertJsonPath('data.branding_removed', false);

        $this->givenSubscription($accountId, AccountSubscriptionStatus::ACTIVE);

        $this->getJson("/public/organizers/$organizerId")
            ->assertOk()
            ->assertJsonPath('data.branding_removed', true);
    }

    public function test_public_organizer_branding_is_never_removed_outside_hi_events(): void
    {
        config(['app.is_hi_events' => false]);
        [, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        DB::table('organizers')->where('id', $organizerId)->update(['status' => OrganizerStatus::LIVE->name]);
        $this->givenSubscription($accountId, AccountSubscriptionStatus::ACTIVE);

        $this->getJson("/public/organizers/$organizerId")
            ->assertOk()
            ->assertJsonPath('data.branding_removed', false);
    }

    public function test_public_order_reports_whether_the_organizers_branding_is_removed(): void
    {
        config(['app.is_hi_events' => true]);
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEvent($accountId, $this->createOrganizer($accountId));
        $shortId = 'o_'.uniqid();
        DB::table('orders')->insert([
            'short_id' => $shortId,
            'event_id' => $eventId,
            'currency' => 'USD',
            'status' => 'COMPLETED',
            'public_id' => 'PUB_'.uniqid(),
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'email' => 'ada@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/public/events/$eventId/order/$shortId?include=event")
            ->assertOk()
            ->assertJsonPath('data.event.organizer.branding_removed', false);

        $this->givenSubscription($accountId, AccountSubscriptionStatus::ACTIVE);

        $this->getJson("/public/events/$eventId/order/$shortId?include=event")
            ->assertOk()
            ->assertJsonPath('data.event.organizer.branding_removed', true);
    }

    private function givenSubscription(int $accountId, AccountSubscriptionStatus $status, bool $cancelAtPeriodEnd = false): void
    {
        DB::table('account_subscriptions')->insert([
            'account_id' => $accountId,
            'stripe_customer_id' => 'cus_'.uniqid(),
            'stripe_subscription_id' => 'sub_'.uniqid(),
            'status' => $status->name,
            'current_period_end' => now()->addMonth(),
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
