<?php

namespace Tests\Unit\Services\Domain\Email\Ses\EventHandlers;

use HiEvents\DomainObjects\OutgoingTransactionMessageDomainObject;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OutgoingTransactionMessageRepositoryInterface;
use HiEvents\Services\Domain\Email\Ses\EventHandlers\DeliveryHandler;
use Illuminate\Log\Logger;
use Mockery as m;
use Tests\TestCase;

class DeliveryHandlerTest extends TestCase
{
    private OutgoingMessageRepositoryInterface $outgoingMessageRepository;
    private OutgoingTransactionMessageRepositoryInterface $outgoingTransactionMessageRepository;
    private Logger $logger;
    private DeliveryHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outgoingMessageRepository = m::mock(OutgoingMessageRepositoryInterface::class);
        $this->outgoingTransactionMessageRepository = m::mock(OutgoingTransactionMessageRepositoryInterface::class);
        $this->outgoingTransactionMessageRepository->shouldReceive('findBySesMessageId')->andReturn(null)->byDefault();
        $this->logger = m::mock(Logger::class)->shouldIgnoreMissing();

        $this->handler = new DeliveryHandler(
            $this->outgoingMessageRepository,
            $this->outgoingTransactionMessageRepository,
            $this->logger,
        );
    }

    public function testMarksOutgoingMessageAsDelivered(): void
    {
        $message = [
            'delivery' => ['recipients' => ['test@example.com']],
            'mail' => ['messageId' => 'ses-msg-001'],
        ];

        $this->outgoingMessageRepository->shouldReceive('markAsDelivered')
            ->with('ses-msg-001')
            ->once()
            ->andReturn(true);

        $this->handler->handle($message, ['MessageId' => 'sns-123']);
    }

    public function testMarksTransactionMessageAsDelivered(): void
    {
        $message = [
            'delivery' => ['recipients' => ['test@example.com']],
            'mail' => ['messageId' => 'ses-msg-002'],
        ];

        $this->outgoingMessageRepository->shouldReceive('markAsDelivered')
            ->with('ses-msg-002')
            ->andReturn(false);

        $transactionMessage = m::mock(OutgoingTransactionMessageDomainObject::class);
        $transactionMessage->shouldReceive('getId')->andReturn(42);

        $this->outgoingTransactionMessageRepository->shouldReceive('findBySesMessageId')
            ->with('ses-msg-002')
            ->andReturn($transactionMessage);

        $this->outgoingTransactionMessageRepository->shouldReceive('markAsDelivered')
            ->with(42)
            ->once();

        $this->handler->handle($message, ['MessageId' => 'sns-456']);
    }

    public function testSkipsWhenNoSesMessageId(): void
    {
        $message = [
            'delivery' => ['recipients' => ['test@example.com']],
        ];

        $this->outgoingMessageRepository->shouldNotReceive('markAsDelivered');
        $this->outgoingTransactionMessageRepository->shouldNotReceive('findBySesMessageId');

        $this->handler->handle($message, ['MessageId' => 'sns-789']);
    }
}
