<?php

namespace Tests\Unit\Services\Domain\Message;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Jobs\Event\SendMessagesJob;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Services\Domain\Message\MessageDispatchService;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class MessageDispatchServiceTest extends TestCase
{
    private MessageRepositoryInterface|MockInterface $messageRepository;

    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private MessageDispatchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->messageRepository = Mockery::mock(MessageRepositoryInterface::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);

        $this->service = new MessageDispatchService(
            $this->messageRepository,
            $this->occurrenceRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_message_for_cancelled_occurrence_is_cancelled_and_not_dispatched(): void
    {
        $message = $this->makeMessage(occurrenceId: 5);

        $occurrence = (new EventOccurrenceDomainObject)->setStatus(EventOccurrenceStatus::CANCELLED->name);
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 5])
            ->andReturn($occurrence);

        $this->messageRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, ['status' => MessageStatus::CANCELLED->name]);

        $this->messageRepository->shouldNotReceive('updateWhere');

        $this->service->dispatchMessage($message);

        Bus::assertNotDispatched(SendMessagesJob::class);
    }

    public function test_message_for_deleted_occurrence_is_cancelled_and_not_dispatched(): void
    {
        $message = $this->makeMessage(occurrenceId: 6);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 6])
            ->andReturnNull();

        $this->messageRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, ['status' => MessageStatus::CANCELLED->name]);

        $this->service->dispatchMessage($message);

        Bus::assertNotDispatched(SendMessagesJob::class);
    }

    public function test_message_for_active_occurrence_is_dispatched(): void
    {
        $message = $this->makeMessage(occurrenceId: 7);

        $occurrence = (new EventOccurrenceDomainObject)->setStatus(EventOccurrenceStatus::ACTIVE->name);
        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 7])
            ->andReturn($occurrence);

        $this->messageRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                ['status' => MessageStatus::PROCESSING->name],
                ['id' => 1, 'status' => MessageStatus::SCHEDULED->name],
            )
            ->andReturn(1);

        $this->service->dispatchMessage($message);

        Bus::assertDispatched(SendMessagesJob::class);
    }

    public function test_message_without_occurrence_scope_is_dispatched(): void
    {
        $message = $this->makeMessage(occurrenceId: null);

        $this->occurrenceRepository->shouldNotReceive('findFirstWhere');

        $this->messageRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->andReturn(1);

        $this->service->dispatchMessage($message);

        Bus::assertDispatched(SendMessagesJob::class);
    }

    private function makeMessage(?int $occurrenceId): MessageDomainObject
    {
        $message = new MessageDomainObject;
        $message->setId(1);
        $message->setEventId(10);
        $message->setSubject('Subject');
        $message->setMessage('Body');
        $message->setType('ALL_ATTENDEES');
        $message->setSentByUserId(1);
        $message->setEventOccurrenceId($occurrenceId);
        $message->setSendData([
            'account_id' => 99,
            'send_copy_to_current_user' => false,
        ]);

        return $message;
    }
}
