<?php

namespace Tests\Unit\Services\Application\Handlers\Order\Payment\Stripe;

use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\EventHandlers\TerminalReaderActionFailedHandler;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\DTO\StripeWebhookDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\IncomingWebhookHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\AccountUpdateHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\ChargeRefundUpdatedHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\ChargeSucceededHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\CustomerSubscriptionUpdatedHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentFailedHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentSucceededHandler;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PayoutPaidHandler;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionSyncService;
use HiEvents\Services\Infrastructure\Stripe\StripeConfigurationService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Log\Logger;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class IncomingWebhookSubscriptionEventsTest extends TestCase
{
    private const BILLING_SECRET = 'whsec_billing_test';

    private AccountSubscriptionSyncService|MockInterface $syncService;

    private IncomingWebhookHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.webhook_secret' => 'whsec_connect_test',
            'services.stripe.ca_webhook_secret' => null,
            'services.stripe.ie_webhook_secret' => null,
            'services.stripe.billing_webhook_secret' => self::BILLING_SECRET,
            'services.stripe.primary_platform' => null,
        ]);

        $this->syncService = Mockery::mock(AccountSubscriptionSyncService::class);

        $this->handler = new IncomingWebhookHandler(
            $this->unused(ChargeRefundUpdatedHandler::class),
            $this->unused(ChargeSucceededHandler::class),
            $this->unused(PaymentIntentSucceededHandler::class),
            $this->unused(PaymentIntentFailedHandler::class),
            $this->unused(AccountUpdateHandler::class),
            $this->unused(PayoutPaidHandler::class),
            $this->unused(TerminalReaderActionFailedHandler::class),
            new CustomerSubscriptionUpdatedHandler($this->syncService),
            Mockery::mock(Logger::class)->shouldIgnoreMissing(),
            new Repository(new ArrayStore),
            new StripeConfigurationService,
        );
    }

    #[DataProvider('subscriptionEvents')]
    public function test_subscription_events_signed_by_the_billing_endpoint_sync_the_customer(string $type): void
    {
        $this->syncService->shouldReceive('syncCustomer')->with('cus_1')->once();

        $this->handler->handle($this->signedEvent($type));
    }

    public static function subscriptionEvents(): array
    {
        return [
            'created' => ['customer.subscription.created'],
            'updated' => ['customer.subscription.updated'],
            'deleted' => ['customer.subscription.deleted'],
        ];
    }

    public function test_subscription_events_from_connected_accounts_are_ignored(): void
    {
        $this->syncService->shouldNotReceive('syncCustomer');

        $this->handler->handle($this->signedEvent('customer.subscription.updated', connectedAccount: 'acct_organizer'));
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function unused(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    private function signedEvent(string $type, ?string $connectedAccount = null): StripeWebhookDTO
    {
        $payload = json_encode([
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => $type,
            'account' => $connectedAccount,
            'created' => time(),
            'data' => ['object' => ['id' => 'sub_1', 'object' => 'subscription', 'customer' => 'cus_1', 'status' => 'active']],
        ]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, self::BILLING_SECRET);

        return new StripeWebhookDTO(headerSignature: "t=$timestamp,v1=$signature", payload: $payload);
    }
}
