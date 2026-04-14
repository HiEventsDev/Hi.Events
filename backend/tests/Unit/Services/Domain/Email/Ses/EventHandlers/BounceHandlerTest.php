<?php

namespace Tests\Unit\Services\Domain\Email\Ses\EventHandlers;

use HiEvents\DomainObjects\EmailSuppressionDomainObject;
use HiEvents\DomainObjects\OutgoingTransactionMessageDomainObject;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OutgoingTransactionMessageRepositoryInterface;
use HiEvents\Services\Domain\Email\EmailSuppressionService;
use HiEvents\Services\Domain\Email\Ses\EventHandlers\BounceHandler;
use Illuminate\Log\Logger;
use Mockery as m;
use Tests\TestCase;

class BounceHandlerTest extends TestCase
{
    private EmailSuppressionService $suppressionService;
    private OutgoingMessageRepositoryInterface $outgoingMessageRepository;
    private OutgoingTransactionMessageRepositoryInterface $outgoingTransactionMessageRepository;
    private Logger $logger;
    private BounceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suppressionService = m::mock(EmailSuppressionService::class);
        $this->outgoingMessageRepository = m::mock(OutgoingMessageRepositoryInterface::class);
        $this->outgoingMessageRepository->shouldReceive('markAsBounced')->andReturn(false)->byDefault();
        $this->outgoingTransactionMessageRepository = m::mock(OutgoingTransactionMessageRepositoryInterface::class);
        $this->outgoingTransactionMessageRepository->shouldReceive('findBySesMessageId')->andReturn(null)->byDefault();
        $this->outgoingTransactionMessageRepository->shouldReceive('findAccountIdByRecipientEmail')->andReturn(null)->byDefault();
        $this->logger = m::mock(Logger::class)->shouldIgnoreMissing();

        $this->handler = new BounceHandler(
            $this->suppressionService,
            $this->outgoingMessageRepository,
            $this->outgoingTransactionMessageRepository,
            $this->logger,
        );
    }

    public function testProcessesBouncedRecipients(): void
    {
        $message = [
            'bounce' => [
                'bounceType' => 'Permanent',
                'bounceSubType' => 'General',
                'bouncedRecipients' => [
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
            'bounce' => [
                'bounceType' => 'Permanent',
                'bouncedRecipients' => [
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
            'bounce' => [
                'bounceType' => 'Permanent',
                'bouncedRecipients' => [
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
            'bounce' => [
                'bounceType' => 'Permanent',
                'bouncedRecipients' => [
                    ['emailAddress' => 'bounced@example.com'],
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

    public function testMarksTransactionMessageAsBouncedBySesMessageId(): void
    {
        $message = [
            'bounce' => [
                'bounceType' => 'Permanent',
                'bouncedRecipients' => [
                    ['emailAddress' => 'bounced@example.com'],
                ],
            ],
            'mail' => [
                'messageId' => 'ses-msg-002',
            ],
        ];

        $this->outgoingMessageRepository->shouldReceive('findAccountIdByRecipientEmail')
            ->andReturn(1);
        $this->outgoingMessageRepository->shouldReceive('markAsBounced')
            ->with('ses-msg-002')
            ->andReturn(false);

        $transactionMessage = m::mock(OutgoingTransactionMessageDomainObject::class);
        $transactionMessage->shouldReceive('getId')->andReturn(99);

        $this->outgoingTransactionMessageRepository->shouldReceive('findBySesMessageId')
            ->with('ses-msg-002')
            ->andReturn($transactionMessage);

        $this->outgoingTransactionMessageRepository->shouldReceive('markAsBounced')
            ->with(99)
            ->once();

        $suppression = m::mock(EmailSuppressionDomainObject::class);
        $this->suppressionService->shouldReceive('suppressEmail')
            ->once()
            ->andReturn($suppression);

        $this->handler->handle($message, ['MessageId' => 'sns-456']);
    }

    public function testDoesNotMarkWhenNoSesMessageId(): void
    {
        $message = [
            'bounce' => [
                'bounceType' => 'Permanent',
                'bouncedRecipients' => [
                    ['emailAddress' => 'nomatch@example.com'],
                ],
            ],
        ];

        $this->outgoingMessageRepository->shouldReceive('findAccountIdByRecipientEmail')
            ->andReturn(1);

        $this->outgoingMessageRepository->shouldNotReceive('markAsBounced');
        $this->outgoingTransactionMessageRepository->shouldNotReceive('findBySesMessageId');

        $suppression = m::mock(EmailSuppressionDomainObject::class);
        $this->suppressionService->shouldReceive('suppressEmail')
            ->once()
            ->andReturn($suppression);

        $this->handler->handle($message, ['MessageId' => 'sns-789']);
    }
}
