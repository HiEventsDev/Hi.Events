<?php

namespace Tests\Unit\Services\Domain\Email\Ses\EventHandlers;

use HiEvents\DomainObjects\EmailSuppressionDomainObject;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Services\Domain\Email\EmailSuppressionService;
use HiEvents\Services\Domain\Email\Ses\EventHandlers\ComplaintHandler;
use Illuminate\Log\Logger;
use Mockery as m;
use Tests\TestCase;

class ComplaintHandlerTest extends TestCase
{
    private EmailSuppressionService $suppressionService;
    private OutgoingMessageRepositoryInterface $outgoingMessageRepository;
    private Logger $logger;
    private ComplaintHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suppressionService = m::mock(EmailSuppressionService::class);
        $this->outgoingMessageRepository = m::mock(OutgoingMessageRepositoryInterface::class);
        $this->outgoingMessageRepository->shouldReceive('markAsBounced')->andReturn(false)->byDefault();
        $this->logger = m::mock(Logger::class)->shouldIgnoreMissing();

        $this->handler = new ComplaintHandler(
            $this->suppressionService,
            $this->outgoingMessageRepository,
            $this->logger,
        );
    }

    public function testProcessesComplainedRecipients(): void
    {
        $message = [
            'complaint' => [
                'complaintFeedbackType' => 'abuse',
                'complainedRecipients' => [
                    ['emailAddress' => 'user1@example.com'],
                    ['emailAddress' => 'user2@example.com'],
                ],
            ],
        ];

        $snsPayload = ['MessageId' => 'msg-123'];

        $this->outgoingMessageRepository->shouldReceive('findAccountIdByRecipientEmail')
            ->with('user1@example.com')->andReturn(1);
        $this->outgoingMessageRepository->shouldReceive('findAccountIdByRecipientEmail')
            ->with('user2@example.com')->andReturn(2);

        $suppression = m::mock(EmailSuppressionDomainObject::class);

        $this->suppressionService->shouldReceive('suppressEmail')
            ->twice()
            ->andReturn($suppression);

        $this->handler->handle($message, $snsPayload);
    }

    public function testSkipsEmptyEmailAddresses(): void
    {
        $message = [
            'complaint' => [
                'complainedRecipients' => [
                    ['emailAddress' => ''],
                    ['emailAddress' => 'valid@example.com'],
                ],
            ],
        ];

        $this->outgoingMessageRepository->shouldReceive('findAccountIdByRecipientEmail')
            ->once()
            ->with('valid@example.com')
            ->andReturn(null);

        $suppression = m::mock(EmailSuppressionDomainObject::class);

        $this->suppressionService->shouldReceive('suppressEmail')
            ->once()
            ->andReturn($suppression);

        $this->handler->handle($message, ['MessageId' => 'msg-456']);
    }

    public function testLooksUpAccountIdFromRepository(): void
    {
        $message = [
            'complaint' => [
                'complainedRecipients' => [
                    ['emailAddress' => 'test@example.com'],
                ],
            ],
        ];

        $this->outgoingMessageRepository->shouldReceive('findAccountIdByRecipientEmail')
            ->once()
            ->with('test@example.com')
            ->andReturn(42);

        $suppression = m::mock(EmailSuppressionDomainObject::class);

        $this->suppressionService->shouldReceive('suppressEmail')
            ->once()
            ->withArgs(function ($email, $reason, $source, $accountId) {
                return $accountId === 42;
            })
            ->andReturn($suppression);

        $this->handler->handle($message, ['MessageId' => 'msg-789']);
    }

    public function testMarksOutgoingMessageAsBouncedBySesMessageId(): void
    {
        $message = [
            'complaint' => [
                'complaintFeedbackType' => 'abuse',
                'complainedRecipients' => [
                    ['emailAddress' => 'complained@example.com'],
                ],
            ],
            'mail' => [
                'messageId' => 'ses-msg-001',
            ],
        ];

        $this->outgoingMessageRepository->shouldReceive('findAccountIdByRecipientEmail')
            ->andReturn(1);

        $this->outgoingMessageRepository->shouldReceive('markAsBounced')
            ->with('ses-msg-001')
            ->once()
            ->andReturn(true);

        $suppression = m::mock(EmailSuppressionDomainObject::class);
        $this->suppressionService->shouldReceive('suppressEmail')
            ->once()
            ->andReturn($suppression);

        $this->handler->handle($message, ['MessageId' => 'sns-123']);
    }
}
