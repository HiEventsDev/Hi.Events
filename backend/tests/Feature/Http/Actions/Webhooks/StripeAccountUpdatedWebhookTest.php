<?php

namespace Tests\Feature\Http\Actions\Webhooks;

use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StripeAccountUpdatedWebhookTest extends TestCase
{
    use DatabaseTransactions;

    private const WEBHOOK_SECRET = 'whsec_feature_test';

    private int $organizerId;

    private int $systemDefaultConfigId;

    private int $usdConfigId;

    private string $stripeAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.saas_mode_enabled' => true,
            'services.stripe.webhook_secret' => self::WEBHOOK_SECRET,
        ]);

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        $this->systemDefaultConfigId = DB::table('organizer_configurations')
            ->where('is_system_default', true)
            ->whereNull('deleted_at')
            ->value('id');

        $this->usdConfigId = DB::table('organizer_configurations')->insertGetId([
            'name' => 'Standard (USD)',
            'is_system_default' => false,
            'application_fees' => json_encode(['percentage' => 1.25, 'fixed' => 0.60, 'currency' => 'USD']),
            'default_for_currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $this->organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Webhook Test Organizer',
            'email' => 'organizer-'.uniqid().'@test.com',
            'currency' => 'EUR',
            'timezone' => 'UTC',
            'organizer_configuration_id' => $this->systemDefaultConfigId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->stripeAccountId = 'acct_feature_'.uniqid();

        DB::table('organizer_stripe_platforms')->insert([
            'organizer_id' => $this->organizerId,
            'stripe_account_id' => $this->stripeAccountId,
            'stripe_connect_account_type' => 'standard',
            'stripe_connect_platform' => 'ie',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_account_updated_webhook_assigns_currency_default_configuration(): void
    {
        $this->deliverAccountUpdatedWebhook(country: 'US');

        $this->assertSame(
            $this->usdConfigId,
            DB::table('organizers')->where('id', $this->organizerId)->value('organizer_configuration_id'),
        );

        $this->assertNotNull(
            DB::table('organizer_stripe_platforms')
                ->where('stripe_account_id', $this->stripeAccountId)
                ->value('stripe_setup_completed_at'),
        );
    }

    public function test_account_updated_webhook_leaves_custom_configurations_untouched(): void
    {
        $customConfigId = DB::table('organizer_configurations')->insertGetId([
            'name' => 'Negotiated Fees',
            'is_system_default' => false,
            'application_fees' => json_encode(['percentage' => 0.5, 'fixed' => 0, 'currency' => 'USD']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organizers')
            ->where('id', $this->organizerId)
            ->update(['organizer_configuration_id' => $customConfigId]);

        $this->deliverAccountUpdatedWebhook(country: 'US');

        $this->assertSame(
            $customConfigId,
            DB::table('organizers')->where('id', $this->organizerId)->value('organizer_configuration_id'),
        );
    }

    private function deliverAccountUpdatedWebhook(string $country): void
    {
        $timestamp = time();

        $payload = json_encode([
            'id' => 'evt_feature_'.uniqid(),
            'object' => 'event',
            'api_version' => '2024-06-20',
            'created' => $timestamp,
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => $this->stripeAccountId,
                    'object' => 'account',
                    'country' => $country,
                    'charges_enabled' => true,
                    'payouts_enabled' => true,
                    'type' => 'standard',
                    'business_type' => 'individual',
                    'capabilities' => [],
                    'requirements' => [
                        'currently_due' => [],
                        'eventually_due' => [],
                        'past_due' => [],
                        'pending_verification' => [],
                    ],
                ],
            ],
            'livemode' => false,
            'pending_webhooks' => 1,
            'request' => ['id' => null, 'idempotency_key' => null],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, self::WEBHOOK_SECRET);

        $response = $this->call(
            method: 'POST',
            uri: '/public/webhooks/stripe',
            server: [
                'HTTP_STRIPE_SIGNATURE' => sprintf('t=%d,v1=%s', $timestamp, $signature),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $payload,
        );

        $response->assertNoContent();
    }
}
