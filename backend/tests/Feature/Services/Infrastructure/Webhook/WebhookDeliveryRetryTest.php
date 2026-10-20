<?php

namespace Tests\Feature\Services\Infrastructure\Webhook;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HiEvents\Jobs\Webhook\SecureCallWebhookJob;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\Webhook\WebhookDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class WebhookDeliveryRetryTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private const WEBHOOK_QUEUE = 'webhook-queue';

    private int $orderId;

    private int $webhookId;

    /** @var array<int, array{request: RequestInterface}> */
    private array $sentRequests = [];

    protected function setUp(): void
    {
        parent::setUp();

        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEvent($accountId, $this->createOrganizer($accountId));

        $this->orderId = DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
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

        $this->webhookId = DB::table('webhooks')->insertGetId([
            'url' => 'http://127.0.0.1/hook',
            'event_types' => json_encode([DomainEventType::ORDER_UPDATED->value]),
            'status' => 'ENABLED',
            'secret' => 'test-secret',
            'user_id' => $owner->id,
            'account_id' => $accountId,
            'event_id' => $eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Config::set('app.allowed_internal_webhook_hosts', '127.0.0.1');
        Config::set('queue.webhook_queue_name', self::WEBHOOK_QUEUE);
    }

    public function test_each_delivery_is_queued_on_the_webhook_queue_with_retries(): void
    {
        Queue::fake();

        app(WebhookDispatchService::class)->dispatchOrderWebhook(DomainEventType::ORDER_UPDATED, $this->orderId);

        Queue::assertPushedOn(
            self::WEBHOOK_QUEUE,
            SecureCallWebhookJob::class,
            fn (SecureCallWebhookJob $job) => $job->connection === null && $job->tries === 3,
        );
    }

    public function test_a_failed_delivery_is_retried_and_every_attempt_is_logged(): void
    {
        $this->fakeResponses([new Response(500, [], 'down'), new Response(200, [], 'ok')]);
        $job = $this->queuedDelivery();

        $this->attempt($job, 1)->assertReleased(10);
        $this->attempt($job, 2)->assertNotReleased();

        $this->assertCount(2, $this->sentRequests);
        $first = $this->sentRequests[0]['request'];
        $retry = $this->sentRequests[1]['request'];
        $this->assertSame((string) $first->getBody(), (string) $retry->getBody());
        $this->assertSame($first->getHeaderLine('Signature'), $retry->getHeaderLine('Signature'));

        $this->assertSame([500, 200], $this->loggedResponseCodes());
        $this->assertSame(200, DB::table('webhooks')->where('id', $this->webhookId)->value('last_response_code'));
    }

    public function test_the_last_failed_attempt_gives_up(): void
    {
        $this->fakeResponses([new ConnectException('refused', new Request('POST', 'http://127.0.0.1/hook'))]);
        $job = $this->queuedDelivery();

        $attempt = $this->attempt($job, 3);

        $attempt->assertNotReleased();
        $attempt->assertDeleted();
        $this->assertSame([0], $this->loggedResponseCodes());
    }

    public function test_a_payload_too_large_for_the_queue_is_delivered_inline_instead(): void
    {
        Queue::fake();
        DB::table('orders')->where('id', $this->orderId)->update(['notes' => str_repeat('n', 160 * 1024)]);

        app(WebhookDispatchService::class)->dispatchOrderWebhook(DomainEventType::ORDER_UPDATED, $this->orderId);

        $pushed = Queue::pushed(SecureCallWebhookJob::class);
        $this->assertCount(1, $pushed);
        $job = $pushed->first();
        $this->assertSame('sync', $job->connection);
        $this->assertNotSame(self::WEBHOOK_QUEUE, $job->queue);
        $this->assertSame(160 * 1024, strlen($job->payload['payload']->notes));
    }

    private function queuedDelivery(): SecureCallWebhookJob
    {
        Queue::fake();

        app(WebhookDispatchService::class)->dispatchOrderWebhook(DomainEventType::ORDER_UPDATED, $this->orderId);

        $pushed = Queue::pushed(SecureCallWebhookJob::class);
        $this->assertCount(1, $pushed);

        return $pushed->first();
    }

    private function attempt(SecureCallWebhookJob $job, int $attempt): SecureCallWebhookJob
    {
        /** @var SecureCallWebhookJob $worker */
        $worker = unserialize(serialize($job));
        $worker->withFakeQueueInteractions();
        $worker->job->attempts = $attempt;

        $worker->handle();

        return $worker;
    }

    private function fakeResponses(array $responses): void
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($this->sentRequests));
        $this->app->instance(Client::class, new Client(['handler' => $handler]));
    }

    private function loggedResponseCodes(): array
    {
        return DB::table('webhook_logs')
            ->where('webhook_id', $this->webhookId)
            ->orderBy('id')
            ->pluck('response_code')
            ->map(fn ($code) => (int) $code)
            ->all();
    }
}
