<?php

namespace Tests\Unit\Jobs\Event;

use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\Status\OutgoingMessageStatus;
use HiEvents\Jobs\Event\SendEventEmailJob;
use HiEvents\Mail\Event\EventMessage;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\PendingMail;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SendEventEmailJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_record_failure_after_a_successful_send_does_not_rethrow(): void
    {
        $mailer = Mockery::mock(Mailer::class);
        $pendingMail = Mockery::mock(PendingMail::class);
        $mailer->shouldReceive('to')->andReturn($pendingMail);
        $pendingMail->shouldReceive('send')->once();

        $outgoingMessageRepository = Mockery::mock(OutgoingMessageRepositoryInterface::class);
        $outgoingMessageRepository
            ->shouldReceive('create')
            ->once()
            ->andThrow(new RuntimeException('db blip'));

        $job = $this->makeJob();
        $job->handle($mailer, $outgoingMessageRepository);

        $this->assertTrue(true);
    }

    public function test_send_failure_records_failed_row_and_rethrows(): void
    {
        $mailer = Mockery::mock(Mailer::class);
        $pendingMail = Mockery::mock(PendingMail::class);
        $mailer->shouldReceive('to')->andReturn($pendingMail);
        $pendingMail->shouldReceive('send')->once()->andThrow(new RuntimeException('smtp down'));

        $outgoingMessageRepository = Mockery::mock(OutgoingMessageRepositoryInterface::class);
        $outgoingMessageRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $attributes) => $attributes['status'] === OutgoingMessageStatus::FAILED->name));

        $this->expectException(RuntimeException::class);

        $job = $this->makeJob();
        $job->handle($mailer, $outgoingMessageRepository);
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
