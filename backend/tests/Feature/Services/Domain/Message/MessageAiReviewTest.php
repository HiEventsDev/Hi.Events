<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\Message;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Jobs\Event\SendMessagesJob;
use HiEvents\Jobs\Message\MessagePendingReviewJob;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Services\Domain\Message\Review\MessageAutoReviewService;
use HiEvents\Services\Infrastructure\Ai\Agents\MessageReviewAgent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class MessageAiReviewTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private int $messageId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.message_ai_review_confidence_threshold' => 0.8]);
        Bus::fake();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $this->messageId = DB::table('messages')->insertGetId([
            'event_id' => $this->eventId,
            'subject' => 'Doors open at 7',
            'message' => '<p>See you there</p>',
            'type' => MessageTypeEnum::ALL_ATTENDEES->name,
            'sent_by_user_id' => $this->userId,
            'status' => MessageStatus::PENDING_REVIEW->name,
            'eligibility_failures' => json_encode(['event_too_new']),
            'send_data' => json_encode(['account_id' => $this->accountId, 'is_test' => false, 'send_copy_to_current_user' => false]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_confident_approval_stores_the_verdict_and_releases_the_message(): void
    {
        MessageReviewAgent::fake([['approve' => true, 'confidence' => 0.93, 'reasons' => ['Event reminder']]]);

        app(MessageAutoReviewService::class)->review($this->messageId, 40);

        $message = app(MessageRepositoryInterface::class)->findFirst($this->messageId);

        $this->assertSame(MessageStatus::PROCESSING->name, $message->getStatus());
        $this->assertSame('APPROVED', $message->aiReviewVerdict()['decision']);
        $this->assertSame(['Event reminder'], $message->aiReviewVerdict()['reasons']);
        Bus::assertDispatched(SendMessagesJob::class);
        Bus::assertNotDispatched(MessagePendingReviewJob::class);
    }

    public function test_an_unconfident_approval_stores_the_verdict_and_leaves_the_message_for_a_person(): void
    {
        MessageReviewAgent::fake([['approve' => true, 'confidence' => 0.5, 'reasons' => ['Unsure']]]);

        app(MessageAutoReviewService::class)->review($this->messageId, 40);

        $message = app(MessageRepositoryInterface::class)->findFirst($this->messageId);

        $this->assertSame(MessageStatus::PENDING_REVIEW->name, $message->getStatus());
        $this->assertSame('NEEDS_PERSON', $message->aiReviewVerdict()['decision']);
        Bus::assertDispatched(MessagePendingReviewJob::class);
        Bus::assertNotDispatched(SendMessagesJob::class);
    }
}
