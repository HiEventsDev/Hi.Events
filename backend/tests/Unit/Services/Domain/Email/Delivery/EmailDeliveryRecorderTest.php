<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Email\Delivery;

use HiEvents\Repository\Interfaces\EmailDeliveryRepositoryInterface;
use HiEvents\Services\Domain\Email\Delivery\EmailDeliveryRecorder;
use HiEvents\Services\Domain\Email\Delivery\EmailProviderMessageIdResolver;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EmailDeliveryRecorderTest extends TestCase
{
    private EmailDeliveryRepositoryInterface|MockInterface $repository;

    private LoggerInterface|MockInterface $logger;

    private EmailDeliveryRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(EmailDeliveryRepositoryInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->recorder = new EmailDeliveryRecorder($this->repository, new EmailProviderMessageIdResolver, $this->logger);
    }

    public function test_records_the_category_and_the_message_id_the_transport_reported(): void
    {
        $sent = $this->sentMessage();
        $sent->setMessageId('pm-123');

        $this->repository->shouldReceive('create')->once()->with([
            'category' => 'attendee_ticket',
            'provider_message_id' => 'pm-123',
        ]);

        $this->recorder->record($sent, 'HiEvents\\Mail\\Attendee\\AttendeeTicketMail');
    }

    public function test_strips_email_suffixes_from_the_category(): void
    {
        $this->repository->shouldReceive('create')->once()->withArgs(
            static fn (array $attributes) => $attributes['category'] === 'ticket_lookup',
        );

        $this->recorder->record($this->sentMessage(), 'HiEvents\\Mail\\TicketLookup\\TicketLookupEmail');
    }

    public function test_keeps_class_names_without_a_suffix(): void
    {
        $this->repository->shouldReceive('create')->once()->withArgs(
            static fn (array $attributes) => $attributes['category'] === 'order_summary',
        );

        $this->recorder->record($this->sentMessage(), 'HiEvents\\Mail\\Order\\OrderSummary');
    }

    public function test_records_no_category_when_the_mailable_is_unknown(): void
    {
        $this->repository->shouldReceive('create')->once()->withArgs(
            static fn (array $attributes) => $attributes['category'] === null,
        );

        $this->recorder->record($this->sentMessage(), null);
    }

    public function test_prefers_the_ses_message_id_header(): void
    {
        $sent = $this->sentMessage();
        $sent->setMessageId('smtp-generated-id');
        $sent->getOriginalMessage()->getHeaders()->addHeader('X-Message-ID', 'ses-0100');

        $this->repository->shouldReceive('create')->once()->withArgs(
            static fn (array $attributes) => $attributes['provider_message_id'] === 'ses-0100',
        );

        $this->recorder->record($sent, null);
    }

    public function test_a_recording_failure_is_logged_and_never_thrown(): void
    {
        $this->repository->shouldReceive('create')->once()->andThrow(new RuntimeException('database unavailable'));
        $this->logger->shouldReceive('error')->once()->with('Failed to record an outgoing email delivery', Mockery::type('array'));

        $this->recorder->record($this->sentMessage(), 'HiEvents\\Mail\\Order\\OrderSummary');
    }

    private function sentMessage(): SentMessage
    {
        $email = (new Email)
            ->from('noreply@example.com')
            ->to('person@example.com')
            ->subject('Subject')
            ->text('Body');

        return new SentMessage($email, new Envelope(new Address('noreply@example.com'), [new Address('person@example.com')]));
    }
}
