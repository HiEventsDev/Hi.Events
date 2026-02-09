<?php

namespace Tests\Unit\Jobs\Message;

use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Jobs\Event\SendMessagesJob;
use HiEvents\Jobs\Message\SendScheduledMessagesJob;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Services\Domain\Message\MessageDispatchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Mockery as m;
use RuntimeException;
use Tests\TestCase;

class SendScheduledMessagesJobTest extends TestCase
{
    private MessageRepositoryInterface $messageRepository;
    private MessageDispatchService $messageDispatchService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->messageRepository = m::mock(MessageRepositoryInterface::class);
        $this->messageDispatchService = m::mock(MessageDispatchService::class);
    }

    public function testPicksUpScheduledMessagesWithPastScheduledAt(): void
    {
        $message = m::mock(MessageDomainObject::class);

        $this->messageRepository->shouldReceive('findWhere')
            ->once()
            ->withArgs(function ($where) {
                return $where['status'] === MessageStatus::SCHEDULED->name
                    && $where[0][0] === 'scheduled_at'
                    && $where[0][1] === '<=';
            })
            ->andReturn(new Collection([$message]));

        $this->messageDispatchService->shouldReceive('dispatchMessage')
            ->once()
            ->with($message);

        $job = new SendScheduledMessagesJob();
        $job->handle($this->messageRepository, $this->messageDispatchService);
    }

    public function testDoesNotPickUpFutureScheduledMessages(): void
    {
        $this->messageRepository->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([]));

        $this->messageDispatchService->shouldNotReceive('dispatchMessage');

        $job = new SendScheduledMessagesJob();
        $job->handle($this->messageRepository, $this->messageDispatchService);
    }

    public function testDoesNotPickUpCancelledMessages(): void
    {
        $this->messageRepository->shouldReceive('findWhere')
            ->once()
            ->withArgs(function ($where) {
                return $where['status'] === MessageStatus::SCHEDULED->name;
            })
            ->andReturn(new Collection([]));

        $this->messageDispatchService->shouldNotReceive('dispatchMessage');

        $job = new SendScheduledMessagesJob();
        $job->handle($this->messageRepository, $this->messageDispatchService);
    }

    public function testContinuesProcessingWhenOneMessageFails(): void
    {
        $message1 = m::mock(MessageDomainObject::class);
        $message1->shouldReceive('getId')->andReturn(1);
        $message2 = m::mock(MessageDomainObject::class);
        $message2->shouldReceive('getId')->andReturn(2);

        $this->messageRepository->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$message1, $message2]));

        $this->messageDispatchService->shouldReceive('dispatchMessage')
            ->once()
            ->with($message1)
            ->andThrow(new RuntimeException('Queue down'));

        $this->messageDispatchService->shouldReceive('dispatchMessage')
            ->once()
            ->with($message2);

        $job = new SendScheduledMessagesJob();
        $job->handle($this->messageRepository, $this->messageDispatchService);
    }
}
