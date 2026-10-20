<?php

namespace Tests\Feature\Services\Infrastructure\Webhook;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\Webhook\WebhookDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class WebhookDispatchServiceTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private const ORDER_ITEM_MONEY = [
        'price' => 25.5,
        'price_before_discount' => 30,
        'total_before_additions' => 51,
    ];

    private int $accountId;

    private int $eventId;

    private int $orderId;

    private string $orderShortId;

    /** @var array<int, array{request: RequestInterface}> */
    private array $sentRequests = [];

    protected function setUp(): void
    {
        parent::setUp();

        [$owner, $this->accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($this->accountId);
        $this->eventId = $this->createEvent($this->accountId, $organizerId);
        $this->orderShortId = 'ord_'.uniqid();
        $this->orderId = $this->createOrderWithItem();

        DB::table('webhooks')->insert([
            'url' => 'http://127.0.0.1/hook',
            'event_types' => json_encode([DomainEventType::ORDER_UPDATED->value]),
            'status' => 'ENABLED',
            'secret' => 'test-secret',
            'user_id' => $owner->id,
            'account_id' => $this->accountId,
            'event_id' => $this->eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Config::set('app.allowed_internal_webhook_hosts', '127.0.0.1');

        $handler = HandlerStack::create(new MockHandler([new Response(200, [], 'ok')]));
        $handler->push(Middleware::history($this->sentRequests));
        $this->app->instance(Client::class, new Client(['handler' => $handler]));
    }

    public function test_order_webhook_keeps_item_prices_when_a_member_without_financials_triggers_it_on_a_sync_queue(): void
    {
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);

        $response = $this->putJson("/events/{$this->eventId}/orders/{$this->orderId}", [
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'email' => 'ada@example.test',
        ], $this->headersFor($member, $this->accountId))->assertOk();

        $this->assertArrayNotHasKey('total_gross', $response->json('data'));
        $this->assertSentPayloadHasFullPrices();
        $this->assertLoggedPayloadHasFullPrices();
    }

    public function test_order_webhook_keeps_item_prices_when_dispatched_from_a_queue_worker(): void
    {
        $this->app['auth']->forgetGuards();

        app(WebhookDispatchService::class)->dispatchOrderWebhook(DomainEventType::ORDER_UPDATED, $this->orderId);

        $this->assertSentPayloadHasFullPrices();
        $this->assertLoggedPayloadHasFullPrices();
    }

    private function assertSentPayloadHasFullPrices(): void
    {
        $this->assertCount(1, $this->sentRequests);

        $body = json_decode((string) $this->sentRequests[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(DomainEventType::ORDER_UPDATED->value, $body['event_type']);
        $this->assertFullPrices($body['payload']);
    }

    private function assertLoggedPayloadHasFullPrices(): void
    {
        $logged = DB::table('webhook_logs')->latest('id')->value('payload');

        $this->assertNotNull($logged);
        $this->assertFullPrices(json_decode($logged, true, flags: JSON_THROW_ON_ERROR)['payload']);
    }

    private function assertFullPrices(array $payload): void
    {
        $this->assertSame($this->orderId, $payload['id']);
        $this->assertSame($this->orderShortId, $payload['short_id']);
        $this->assertEquals(56.1, $payload['total_gross']);
        $this->assertEquals(5.1, $payload['total_tax']);

        $item = $payload['order_items'][0];
        foreach (self::ORDER_ITEM_MONEY as $key => $amount) {
            $this->assertArrayHasKey($key, $item);
            $this->assertEquals($amount, $item[$key], $key);
        }
        $this->assertSame([['name' => 'VAT', 'rate' => 10, 'value' => 5.1]], $item['taxes_and_fees_rollup']['taxes']);
    }

    private function createOrderWithItem(): int
    {
        $productId = DB::table('products')->insertGetId([
            'title' => 'General Admission',
            'event_id' => $this->eventId,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productPriceId = DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'short_id' => $this->orderShortId,
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => 'COMPLETED',
            'public_id' => 'PUB_'.uniqid(),
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'email' => 'ada@example.test',
            'total_before_additions' => 51,
            'total_gross' => 56.1,
            'total_tax' => 5.1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $productPriceId,
            'item_name' => 'General Admission',
            'quantity' => 2,
            'total_tax' => 5.1,
            'total_gross' => 56.1,
            'taxes_and_fees_rollup' => json_encode(['fees' => [], 'taxes' => [['name' => 'VAT', 'rate' => 10, 'value' => 5.1]]]),
            ...self::ORDER_ITEM_MONEY,
        ]);

        return $orderId;
    }
}
