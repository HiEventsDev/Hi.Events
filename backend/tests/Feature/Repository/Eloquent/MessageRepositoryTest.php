<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class MessageRepositoryTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
    }

    public function test_delivered_test_sends_do_not_count_towards_the_daily_message_limit(): void
    {
        $this->insertMessage(MessageStatus::SENT, ['is_test' => false]);
        $this->insertMessage(MessageStatus::PENDING_REVIEW, ['is_test' => false]);
        $this->insertMessage(MessageStatus::SENT, null);
        $this->insertMessage(MessageStatus::SENT, ['is_test' => true]);
        $this->insertMessage(MessageStatus::PROCESSING, ['is_test' => true]);
        $this->insertMessage(MessageStatus::CANCELLED, ['is_test' => false]);

        $this->assertSame(3, app(MessageRepositoryInterface::class)->countMessagesInLast24Hours($this->accountId));
    }

    public function test_test_sends_held_for_review_still_count_so_reviews_stay_capped(): void
    {
        $this->insertMessage(MessageStatus::PENDING_REVIEW, ['is_test' => true]);
        $this->insertMessage(MessageStatus::PENDING_REVIEW, ['is_test' => true]);

        $this->assertSame(2, app(MessageRepositoryInterface::class)->countMessagesInLast24Hours($this->accountId));
    }

    private function insertMessage(MessageStatus $status, ?array $sendData): void
    {
        DB::table('messages')->insert([
            'event_id' => $this->eventId,
            'subject' => 'Doors open at 7',
            'message' => '<p>See you there</p>',
            'type' => MessageTypeEnum::ALL_ATTENDEES->name,
            'sent_by_user_id' => $this->userId,
            'status' => $status->name,
            'send_data' => $sendData === null ? null : json_encode($sendData),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
