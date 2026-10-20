<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Actions\Messages;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class GetMessageRecipientsTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private string $authToken;

    private int $messageId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));

        $this->messageId = DB::table('messages')->insertGetId([
            'event_id' => $this->eventId,
            'subject' => 'Doors open at 7',
            'message' => 'See you there',
            'type' => 'ALL_ATTENDEES',
            'status' => 'SENT',
            'sent_by_user_id' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_each_recipient_carries_the_delivery_events_of_its_email(): void
    {
        $this->insertRecipient('delivered@example.com', 'SENT', 'pm-delivered');
        $this->insertRecipient('bounced@example.com', 'SENT', 'pm-bounced');
        $this->insertRecipient('pending@example.com', 'SENT', 'pm-pending');
        $this->insertRecipient('untracked@example.com', 'SENT', null);
        $this->insertRecipient('failed@example.com', 'FAILED', null);

        $this->insertDelivery('pm-delivered', ['delivered_at' => '2026-10-14 10:00:00']);
        $this->insertDelivery('pm-bounced', ['bounced_at' => '2026-10-14 10:05:00', 'bounce_type' => 'HARD']);
        $this->insertDelivery('pm-pending', []);

        $recipients = collect($this->getRecipients()->assertOk()->json('data'))->keyBy('recipient');

        $this->assertNotNull($recipients['delivered@example.com']['delivered_at']);
        $this->assertNull($recipients['delivered@example.com']['bounced_at']);
        $this->assertNotNull($recipients['bounced@example.com']['bounced_at']);
        $this->assertNull($recipients['pending@example.com']['delivered_at']);
        $this->assertNull($recipients['untracked@example.com']['delivered_at']);
        $this->assertSame('FAILED', $recipients['failed@example.com']['status']);
        $this->assertNull($recipients['failed@example.com']['complained_at']);
    }

    public function test_recipients_of_another_account_are_not_visible(): void
    {
        $this->insertRecipient('delivered@example.com', 'SENT', 'pm-delivered');
        $otherUser = User::factory()->withAccount()->create();
        $this->authToken = JWTAuth::claims(['account_id' => $otherUser->accounts()->first()->id])->fromUser($otherUser);

        $this->assertContains($this->getRecipients()->status(), [403, 404]);
    }

    private function getRecipients()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->authToken)
            ->getJson(sprintf('/events/%d/messages/%d/recipients', $this->eventId, $this->messageId));
    }

    private function insertRecipient(string $email, string $status, ?string $providerMessageId): void
    {
        DB::table('outgoing_messages')->insert([
            'event_id' => $this->eventId,
            'message_id' => $this->messageId,
            'subject' => 'Doors open at 7',
            'recipient' => $email,
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertDelivery(string $providerMessageId, array $attributes): void
    {
        DB::table('email_deliveries')->insert(array_merge([
            'category' => 'event_message',
            'provider_message_id' => $providerMessageId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }
}
