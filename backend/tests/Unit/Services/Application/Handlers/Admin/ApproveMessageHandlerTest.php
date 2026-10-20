<?php

namespace Tests\Unit\Services\Application\Handlers\Admin;

use Carbon\Carbon;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Jobs\Event\SendMessagesJob;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\ApproveMessageHandler;
use HiEvents\Services\Domain\Message\MessageDispatchService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ApproveMessageHandlerTest extends TestCase
{
    private MessageRepositoryInterface|MockInterface $messageRepository;

    private ApproveMessageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->messageRepository = Mockery::mock(MessageRepositoryInterface::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->handler = new ApproveMessageHandler(
            $this->messageRepository,
            $databaseManager,
            new MessageDispatchService(
                $this->messageRepository,
                Mockery::mock(EventOccurrenceRepositoryInterface::class),
            ),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_approved_test_message_is_sent_now_as_a_test(): void
    {
        $message = $this->makeHeldMessage(scheduledAt: null, isTest: true);

        $this->messageRepository->shouldReceive('findFirst')->with(1)->andReturn($message);
        $this->messageRepository->shouldNotReceive('updateFromArray');
        $this->messageRepository->shouldReceive('updateWhere')
            ->once()
            ->with(
                ['status' => MessageStatus::PROCESSING->name],
                ['id' => 1, 'status' => MessageStatus::PENDING_REVIEW->name],
            )
            ->andReturn(1);

        $this->handler->handle(1);

        Bus::assertDispatched(SendMessagesJob::class, fn (SendMessagesJob $job) => $job->messageData->is_test === true);
    }

    public function test_approved_live_message_with_a_future_schedule_is_scheduled(): void
    {
        $message = $this->makeHeldMessage(scheduledAt: Carbon::now()->addDay()->toDateTimeString(), isTest: false);

        $this->messageRepository->shouldReceive('findFirst')->with(1)->andReturn($message);
        $this->messageRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(1, ['status' => MessageStatus::SCHEDULED->name])
            ->andReturn($message);

        $this->handler->handle(1);

        Bus::assertNotDispatched(SendMessagesJob::class);
    }

    private function makeHeldMessage(?string $scheduledAt, bool $isTest): MessageDomainObject
    {
        return (new MessageDomainObject)
            ->setId(1)
            ->setEventId(10)
            ->setSubject('Subject')
            ->setMessage('Body')
            ->setType('ALL_ATTENDEES')
            ->setSentByUserId(5)
            ->setStatus(MessageStatus::PENDING_REVIEW->name)
            ->setScheduledAt($scheduledAt)
            ->setSendData([
                'account_id' => 99,
                'is_test' => $isTest,
                'send_copy_to_current_user' => false,
            ]);
    }
}
