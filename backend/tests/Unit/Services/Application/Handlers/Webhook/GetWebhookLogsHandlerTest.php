<?php

namespace Tests\Unit\Services\Application\Handlers\Webhook;

use HiEvents\DomainObjects\WebhookDomainObject;
use HiEvents\DomainObjects\WebhookLogDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\WebhookLogRepositoryInterface;
use HiEvents\Repository\Interfaces\WebhookRepositoryInterface;
use HiEvents\Services\Application\Handlers\Webhook\GetWebhookLogsHandler;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Tests\TestCase;

class GetWebhookLogsHandlerTest extends TestCase
{
    private WebhookLogRepositoryInterface|MockInterface $webhookLogRepository;

    private WebhookRepositoryInterface|MockInterface $webhookRepository;

    private GetWebhookLogsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webhookLogRepository = Mockery::mock(WebhookLogRepositoryInterface::class);
        $this->webhookRepository = Mockery::mock(WebhookRepositoryInterface::class);

        $this->handler = new GetWebhookLogsHandler(
            $this->webhookLogRepository,
            $this->webhookRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_fetches_the_latest_logs_newest_first(): void
    {
        $this->webhookRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 12, 'account_id' => 1, 'event_id' => 50])
            ->andReturn((new WebhookDomainObject)->setId(12));

        $logs = collect([
            (new WebhookLogDomainObject)->setId(411),
            (new WebhookLogDomainObject)->setId(410),
        ]);

        $this->webhookLogRepository
            ->shouldReceive('findWhere')
            ->once()
            ->withArgs(function (array $where, array $columns, array $orderAndDirections, ?int $limit) {
                return $where === ['webhook_id' => 12]
                    && count($orderAndDirections) === 1
                    && $orderAndDirections[0]->getOrder() === 'id'
                    && $orderAndDirections[0]->getDirection() === OrderAndDirection::DIRECTION_DESC
                    && $limit === 10;
            })
            ->andReturn($logs);

        $result = $this->handler->handle(webhookId: 12, accountId: 1, eventId: 50);

        $this->assertSame($logs, $result);
    }

    public function test_it_scopes_the_webhook_lookup_to_the_organizer(): void
    {
        $this->webhookRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 12, 'account_id' => 1, 'organizer_id' => 7])
            ->andReturn((new WebhookDomainObject)->setId(12));

        $this->webhookLogRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect());

        $result = $this->handler->handle(webhookId: 12, accountId: 1, organizerId: 7);

        $this->assertTrue($result->isEmpty());
    }

    public function test_it_throws_when_the_webhook_does_not_exist(): void
    {
        $this->webhookRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturnNull();

        $this->webhookLogRepository->shouldNotReceive('findWhere');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(webhookId: 12, accountId: 1, eventId: 50);
    }
}
