<?php

namespace Tests\Unit\Jobs\Event;

use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\OutgoingMessageDomainObject;
use HiEvents\DomainObjects\Status\OutgoingMessageStatus;
use HiEvents\Jobs\Event\SendEventEmailJob;
use HiEvents\Mail\Event\EventMessage;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Domain\Email\Delivery\EmailProviderMessageIdResolver;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\PendingMail;
use Illuminate\Mail\SentMessage;
use Mockery;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class SendEventEmailJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_records_the_provider_message_id_of_the_sent_email(): void
    {
        $email = (new Email)->from('sender@example.com')->to('recipient@example.com')->text('Body');
        $email->getHeaders()->addTextHeader('X-Message-ID', 'ses-message-id');
        $sentMessage = new SentMessage(new SymfonySentMessage(
            $email,
            new Envelope(new Address('sender@example.com'), [new Address('recipient@example.com')]),
        ));

        $mailer = Mockery::mock(Mailer::class);
        $pendingMail = Mockery::mock(PendingMail::class);
        $mailer->shouldReceive('to')->andReturn($pendingMail);
        $pendingMail->shouldReceive('sendNow')->once()->andReturn($sentMessage);

        $outgoingMessageRepository = Mockery::mock(OutgoingMessageRepositoryInterface::class);
        $recorded = null;
        $outgoingMessageRepository
            ->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $attributes) use (&$recorded) {
                $recorded = $attributes;

                return Mockery::mock(OutgoingMessageDomainObject::class);
            });

        $job = $this->makeJob();
        $job->handle($mailer, $outgoingMessageRepository, new EmailProviderMessageIdResolver);

        $this->assertSame(OutgoingMessageStatus::SENT->name, $recorded['status']);
        $this->assertSame('ses-message-id', $recorded['provider_message_id']);
    }

    public function test_record_failure_after_a_successful_send_does_not_rethrow(): void
    {
        $mailer = Mockery::mock(Mailer::class);
        $pendingMail = Mockery::mock(PendingMail::class);
        $mailer->shouldReceive('to')->andReturn($pendingMail);
        $pendingMail->shouldReceive('sendNow')->once();

        $outgoingMessageRepository = Mockery::mock(OutgoingMessageRepositoryInterface::class);
        $outgoingMessageRepository
            ->shouldReceive('create')
            ->once()
            ->andThrow(new RuntimeException('db blip'));

        $job = $this->makeJob();
        $job->handle($mailer, $outgoingMessageRepository, new EmailProviderMessageIdResolver);

        $this->assertTrue(true);
    }

    public function test_send_failure_rethrows_without_recording_so_the_job_can_retry(): void
    {
        $mailer = Mockery::mock(Mailer::class);
        $pendingMail = Mockery::mock(PendingMail::class);
        $mailer->shouldReceive('to')->andReturn($pendingMail);
        $pendingMail->shouldReceive('sendNow')->once()->andThrow(new RuntimeException('smtp down'));

        $outgoingMessageRepository = Mockery::mock(OutgoingMessageRepositoryInterface::class);
        $outgoingMessageRepository->shouldNotReceive('create');

        $this->expectException(RuntimeException::class);

        $job = $this->makeJob();
        $job->handle($mailer, $outgoingMessageRepository, new EmailProviderMessageIdResolver);
    }

    public function test_records_a_failed_row_once_the_job_has_failed_for_good(): void
    {
        $recorded = null;
        $outgoingMessageRepository = Mockery::mock(OutgoingMessageRepositoryInterface::class);
        $outgoingMessageRepository
            ->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $attributes) use (&$recorded) {
                $recorded = $attributes;

                return Mockery::mock(OutgoingMessageDomainObject::class);
            });
        $this->app->instance(OutgoingMessageRepositoryInterface::class, $outgoingMessageRepository);

        $this->makeJob()->failed(new RuntimeException('smtp down'));

        $this->assertSame(OutgoingMessageStatus::FAILED->name, $recorded['status']);
        $this->assertSame('recipient@example.com', $recorded['recipient']);
        $this->assertNull($recorded['provider_message_id']);
    }

    private function makeJob(): SendEventEmailJob
    {
        return new SendEventEmailJob(
            email: 'recipient@example.com',
            toName: 'Recipient',
            eventMessage: Mockery::mock(EventMessage::class),
            messageData: new SendMessageDTO(
                account_id: 1,
                event_id: 2,
                subject: 'Subject',
                message: 'Body',
                type: MessageTypeEnum::ALL_ATTENDEES,
                is_test: false,
                send_copy_to_current_user: false,
                sent_by_user_id: 3,
                order_id: null,
                id: 4,
            ),
        );
    }
}
