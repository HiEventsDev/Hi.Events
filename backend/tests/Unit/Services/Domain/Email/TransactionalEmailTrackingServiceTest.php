<?php

namespace Tests\Unit\Services\Domain\Email;

use HiEvents\DomainObjects\Enums\TransactionalEmailType;
use HiEvents\DomainObjects\Status\OutgoingTransactionMessageStatus;
use HiEvents\Repository\Interfaces\OutgoingTransactionMessageRepositoryInterface;
use HiEvents\Services\Domain\Email\EmailSuppressionService;
use HiEvents\Services\Domain\Email\TransactionalEmailTrackingService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailable;
use Mockery as m;
use RuntimeException;
use Tests\TestCase;

class TransactionalEmailTrackingServiceTest extends TestCase
{
    private OutgoingTransactionMessageRepositoryInterface $repository;
    private EmailSuppressionService $suppressionService;
    private Mailer $mailer;
    private TransactionalEmailTrackingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = m::mock(OutgoingTransactionMessageRepositoryInterface::class);
        $this->suppressionService = m::mock(EmailSuppressionService::class);
        $this->mailer = m::mock(Mailer::class);

        $this->service = new TransactionalEmailTrackingService(
            $this->repository,
            $this->suppressionService,
        );
    }

    public function testRecordsSentStatusOnSuccessfulSend(): void
    {
        $mail = m::mock(Mailable::class);
        $pendingMail = m::mock(\Illuminate\Mail\PendingMail::class);

        $this->suppressionService->shouldReceive('isEmailSuppressed')
            ->with('test@example.com', 1, 'transactional')
            ->andReturn(false);

        $this->mailer->shouldReceive('to')
            ->with('test@example.com')
            ->andReturn($pendingMail);
        $pendingMail->shouldReceive('locale')
            ->with('en')
            ->andReturn($pendingMail);
        $sentMessage = m::mock(\Illuminate\Mail\SentMessage::class);
        $sentMessage->shouldReceive('getMessageId')->andReturn('ses-abc-123');

        $pendingMail->shouldReceive('sendNow')
            ->with($mail)
            ->once()
            ->andReturn($sentMessage);

        $this->repository->shouldReceive('create')
            ->once()
            ->withArgs(function ($attrs) {
                return $attrs['status'] === OutgoingTransactionMessageStatus::SENT->value
                    && $attrs['email_type'] === TransactionalEmailType::ORDER_SUMMARY->value
                    && $attrs['recipient'] === 'test@example.com'
                    && $attrs['event_id'] === 10
                    && $attrs['order_id'] === 20
                    && $attrs['ses_message_id'] === 'ses-abc-123';
            });

        $this->service->recordAndSend(
            mailer: $this->mailer,
            recipient: 'test@example.com',
            mail: $mail,
            emailType: TransactionalEmailType::ORDER_SUMMARY,
            subject: 'Your Order is Confirmed!',
            eventId: 10,
            orderId: 20,
            accountId: 1,
            locale: 'en',
        );
    }

    public function testRecordsFailedStatusWhenMailerThrows(): void
    {
        $mail = m::mock(Mailable::class);
        $pendingMail = m::mock(\Illuminate\Mail\PendingMail::class);

        $this->suppressionService->shouldReceive('isEmailSuppressed')
            ->andReturn(false);

        $this->mailer->shouldReceive('to')
            ->andReturn($pendingMail);
        $pendingMail->shouldReceive('sendNow')
            ->andThrow(new RuntimeException('SMTP error'));

        $this->repository->shouldReceive('create')
            ->once()
            ->withArgs(function ($attrs) {
                return $attrs['status'] === OutgoingTransactionMessageStatus::FAILED->value;
            });

        $this->expectException(RuntimeException::class);

        $this->service->recordAndSend(
            mailer: $this->mailer,
            recipient: 'fail@example.com',
            mail: $mail,
            emailType: TransactionalEmailType::ATTENDEE_TICKET,
            subject: 'Your Ticket',
            eventId: 10,
            accountId: 1,
        );
    }

    public function testRecordsSuppressedStatusWhenEmailIsSuppressed(): void
    {
        $mail = m::mock(Mailable::class);

        $this->suppressionService->shouldReceive('isEmailSuppressed')
            ->with('suppressed@example.com', 1, 'transactional')
            ->andReturn(true);

        $this->mailer->shouldNotReceive('to');

        $this->repository->shouldReceive('create')
            ->once()
            ->withArgs(function ($attrs) {
                return $attrs['status'] === OutgoingTransactionMessageStatus::SUPPRESSED->value
                    && $attrs['recipient'] === 'suppressed@example.com'
                    && $attrs['email_type'] === TransactionalEmailType::ORDER_SUMMARY->value;
            });

        $this->service->recordAndSend(
            mailer: $this->mailer,
            recipient: 'suppressed@example.com',
            mail: $mail,
            emailType: TransactionalEmailType::ORDER_SUMMARY,
            subject: 'Your Order',
            eventId: 10,
            accountId: 1,
        );
    }

    public function testPassesCorrectAttendeeIdAndOrderId(): void
    {
        $mail = m::mock(Mailable::class);
        $pendingMail = m::mock(\Illuminate\Mail\PendingMail::class);

        $this->suppressionService->shouldReceive('isEmailSuppressed')
            ->andReturn(false);

        $this->mailer->shouldReceive('to')->andReturn($pendingMail);
        $pendingMail->shouldReceive('locale')->andReturn($pendingMail);
        $pendingMail->shouldReceive('sendNow');

        $this->repository->shouldReceive('create')
            ->once()
            ->withArgs(function ($attrs) {
                return $attrs['event_id'] === 5
                    && $attrs['order_id'] === 15
                    && $attrs['attendee_id'] === 25
                    && $attrs['email_type'] === TransactionalEmailType::ATTENDEE_TICKET->value;
            });

        $this->service->recordAndSend(
            mailer: $this->mailer,
            recipient: 'attendee@example.com',
            mail: $mail,
            emailType: TransactionalEmailType::ATTENDEE_TICKET,
            subject: 'Your Ticket',
            eventId: 5,
            orderId: 15,
            attendeeId: 25,
            accountId: 1,
            locale: 'en',
        );
    }
}
