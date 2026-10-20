<?php

namespace Tests\Feature\Services\Application\Handlers\Order\Payment\Stripe;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\Order\OrderRefunded;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\ChargeRefundUpdatedHandler;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\IdempotencyException;
use Stripe\Refund;
use Stripe\Service\RefundService;
use Stripe\StripeClient;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class StripeRefundFlowTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private const PAYMENT_INTENT_ID = 'pi_refund_flow';

    private int $orderId;

    private RefundService $stripeRefunds;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent();
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('event_statistics')->insert([
            'event_id' => $this->eventId,
            'sales_total_gross' => 40,
            'sales_total_before_additions' => 40,
            'created_at' => now(),
        ]);

        $occurrenceId = $this->insertOccurrence(daysAhead: 10);
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, null, price: 20.00);

        $this->orderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        DB::table('orders')->where('id', $this->orderId)->update([
            'email' => 'buyer@example.test',
            'first_name' => 'Robin',
            'last_name' => 'Buyer',
            'locale' => 'en',
            'total_gross' => 40,
            'total_before_additions' => 40,
            'total_refunded' => 0,
            'payment_provider' => PaymentProviders::STRIPE->name,
            'payment_status' => OrderPaymentStatus::PAYMENT_RECEIVED->name,
        ]);
        $orderItemId = $this->insertOrderItem($this->orderId, $productId, $priceId, $occurrenceId, 2);
        DB::table('order_items')->where('id', $orderItemId)->update(['price' => 20, 'total_before_additions' => 40, 'total_gross' => 40]);

        DB::table('stripe_payments')->insert([
            'order_id' => $this->orderId,
            'payment_intent_id' => self::PAYMENT_INTENT_ID,
            'connected_account_id' => 'acct_refund_flow',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->stripeRefunds = $this->fakeStripeRefunds();

        $stripeClient = Mockery::mock(StripeClient::class);
        $stripeClient->shouldReceive('getService')->with('refunds')->andReturn($this->stripeRefunds);

        $stripeClientFactory = Mockery::mock(StripeClientFactory::class);
        $stripeClientFactory->shouldReceive('createForPlatform')->andReturn($stripeClient);
        $this->app->instance(StripeClientFactory::class, $stripeClientFactory);
    }

    public function test_two_identical_partial_refunds_both_go_through_and_the_order_totals_add_up(): void
    {
        $this->refund(20)->assertOk();
        $this->assertOrder(OrderRefundStatus::REFUND_PENDING, totalRefunded: 0.0);

        $this->stripeSends('re_1', 'pending');
        $this->stripeSends('re_1', 'succeeded');
        $this->assertOrder(OrderRefundStatus::PARTIALLY_REFUNDED, totalRefunded: 20.0);

        $this->refund(20)->assertOk();
        $this->assertSame(['re_1', 're_2'], $this->createdRefundIds());
        $this->assertSame([2000, 2000], array_column($this->stripeRefunds->created, 'amount'));
        $this->assertOrder(OrderRefundStatus::REFUND_PENDING, totalRefunded: 20.0);

        $this->stripeSends('re_2', 'succeeded');

        $this->assertOrder(OrderRefundStatus::REFUNDED, totalRefunded: 40.0);
        $this->assertSame(['re_1', 're_2'], DB::table('order_refunds')->where('order_id', $this->orderId)->orderBy('id')->pluck('refund_id')->all());
        $this->assertEquals(40.0, (float) DB::table('event_statistics')->where('event_id', $this->eventId)->value('total_refunded'));
        $this->assertEquals(0.0, (float) DB::table('event_statistics')->where('event_id', $this->eventId)->value('sales_total_gross'));
        $this->assertRefundEmailsSent([2000, 2000]);
    }

    public function test_a_retry_after_stripe_created_the_refund_but_the_response_was_lost_is_deduplicated(): void
    {
        $this->stripeRefunds->loseNextResponse = true;

        $this->refund(20)->assertUnprocessable();
        $this->assertOrder(null, totalRefunded: 0.0);

        $this->refund(20)->assertOk();

        $this->assertSame(['re_1'], $this->createdRefundIds());
        $this->assertOrder(OrderRefundStatus::REFUND_PENDING, totalRefunded: 0.0);

        $this->stripeSends('re_1', 'succeeded');

        $this->assertOrder(OrderRefundStatus::PARTIALLY_REFUNDED, totalRefunded: 20.0);
        $this->assertRefundEmailsSent([2000]);
    }

    public function test_a_pending_refund_stays_pending_and_blocks_a_second_refund(): void
    {
        $this->refund(20)->assertOk();

        $this->stripeSends('re_1', 'pending', pendingReason: 'insufficient_funds');

        $this->assertOrder(OrderRefundStatus::REFUND_PENDING, totalRefunded: 0.0);
        $this->assertSame(0, DB::table('order_refunds')->where('order_id', $this->orderId)->count());

        $this->refund(10)->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertSame(['re_1'], $this->createdRefundIds());
        $this->assertOrder(OrderRefundStatus::REFUND_PENDING, totalRefunded: 0.0);
        $this->assertRefundEmailsSent([]);

        $this->stripeSends('re_1', 'succeeded');

        $this->assertOrder(OrderRefundStatus::PARTIALLY_REFUNDED, totalRefunded: 20.0);
        $this->assertRefundEmailsSent([2000]);
    }

    public function test_a_refund_that_fails_after_pending_is_failed_and_the_buyer_is_not_told_it_was_refunded(): void
    {
        $this->refund(20)->assertOk();

        $this->stripeSends('re_1', 'pending');
        $this->stripeSends('re_1', 'failed');

        $this->assertOrder(OrderRefundStatus::REFUND_FAILED, totalRefunded: 0.0);
        $this->assertEquals(0.0, (float) DB::table('event_statistics')->where('event_id', $this->eventId)->value('total_refunded'));
        $this->assertRefundEmailsSent([]);
    }

    public function test_retrying_a_failed_refund_for_the_same_amount_creates_a_new_refund(): void
    {
        $this->refund(20)->assertOk();
        $this->stripeSends('re_1', 'failed');
        $this->assertOrder(OrderRefundStatus::REFUND_FAILED, totalRefunded: 0.0);

        $this->refund(20)->assertOk();

        $this->assertSame(['re_1', 're_2'], $this->createdRefundIds());
        $this->assertOrder(OrderRefundStatus::REFUND_PENDING, totalRefunded: 0.0);

        $this->stripeSends('re_2', 'succeeded');

        $this->assertOrder(OrderRefundStatus::PARTIALLY_REFUNDED, totalRefunded: 20.0);
        $this->assertRefundEmailsSent([2000]);
    }

    public function test_the_buyer_is_emailed_once_when_stripe_reports_the_same_success_twice(): void
    {
        $this->refund(20)->assertOk();

        $this->stripeSends('re_1', 'succeeded');
        $this->stripeSends('re_1', 'succeeded');

        $this->assertOrder(OrderRefundStatus::PARTIALLY_REFUNDED, totalRefunded: 20.0);
        $this->assertSame(1, DB::table('order_refunds')->where('order_id', $this->orderId)->count());
        $this->assertRefundEmailsSent([2000]);
    }

    public function test_the_buyer_is_not_emailed_when_the_organizer_turned_the_notification_off(): void
    {
        $this->refund(20, notifyBuyer: false)->assertOk();

        $this->stripeSends('re_1', 'succeeded');

        $this->assertOrder(OrderRefundStatus::PARTIALLY_REFUNDED, totalRefunded: 20.0);
        $this->assertRefundEmailsSent([]);
    }

    private function refund(float $amount, bool $notifyBuyer = true): TestResponse
    {
        $this->app['tymon.jwt']->unsetToken();

        return $this->postJson("/events/{$this->eventId}/orders/{$this->orderId}/refund", [
            'amount' => $amount,
            'notify_buyer' => $notifyBuyer,
            'cancel_order' => false,
        ], ['Authorization' => 'Bearer '.JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId))]);
    }

    private function stripeSends(string $refundId, string $status, ?string $pendingReason = null): void
    {
        $created = collect($this->stripeRefunds->created)->firstWhere('id', $refundId);

        app(ChargeRefundUpdatedHandler::class)->handleEvent(Refund::constructFrom(array_merge($created->toArray(), [
            'status' => $status,
            'pending_reason' => $pendingReason,
        ])));
    }

    private function assertOrder(?OrderRefundStatus $refundStatus, float $totalRefunded): void
    {
        $order = DB::table('orders')->where('id', $this->orderId)->first();

        $this->assertSame($refundStatus?->name, $order->refund_status);
        $this->assertEquals($totalRefunded, (float) $order->total_refunded);
    }

    private function createdRefundIds(): array
    {
        return array_column($this->stripeRefunds->created, 'id');
    }

    private function assertRefundEmailsSent(array $amountsInMinorUnits): void
    {
        $sent = Mail::queued(OrderRefunded::class)
            ->map(function (OrderRefunded $mail) {
                $this->assertTrue($mail->hasTo('buyer@example.test'));

                return $mail->content()->with['refundAmount']->toMinorUnit();
            })
            ->values()
            ->all();

        $this->assertSame($amountsInMinorUnits, $sent);
    }

    private function fakeStripeRefunds(): RefundService
    {
        return new class(Mockery::mock(StripeClient::class)) extends RefundService
        {
            public array $created = [];

            public bool $loseNextResponse = false;

            private array $responsesByIdempotencyKey = [];

            public function create($params = null, $opts = null)
            {
                $key = $opts['idempotency_key'];

                if (isset($this->responsesByIdempotencyKey[$key])) {
                    if ($this->responsesByIdempotencyKey[$key]['params'] !== $params) {
                        throw IdempotencyException::factory('Keys for idempotent requests can only be used with the same parameters they were first used with.');
                    }

                    return $this->responsesByIdempotencyKey[$key]['refund'];
                }

                $refund = Refund::constructFrom([
                    'id' => 're_'.(count($this->created) + 1),
                    'object' => 'refund',
                    'amount' => $params['amount'],
                    'currency' => 'usd',
                    'payment_intent' => $params['payment_intent'],
                    'status' => 'pending',
                    'metadata' => $params['metadata'] ?? [],
                ]);

                $this->responsesByIdempotencyKey[$key] = ['params' => $params, 'refund' => $refund];
                $this->created[] = $refund;

                if ($this->loseNextResponse) {
                    $this->loseNextResponse = false;

                    throw ApiConnectionException::factory('Request timed out');
                }

                return $refund;
            }
        };
    }
}
