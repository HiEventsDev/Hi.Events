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

class SendMessageActionTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private string $authToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));
    }

    public function test_a_message_without_a_recipient_type_is_rejected_and_not_stored(): void
    {
        $this->sendMessage(['message_type' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message_type']);

        $this->assertSame(0, DB::table('messages')->where('event_id', $this->eventId)->count());
    }

    public function test_an_order_owner_message_without_an_order_is_rejected_and_not_stored(): void
    {
        $this->sendMessage(['message_type' => 'ORDER_OWNER'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);

        $this->assertSame(0, DB::table('messages')->where('event_id', $this->eventId)->count());
    }

    private function sendMessage(array $overrides)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->authToken)
            ->postJson(sprintf('/events/%d/messages', $this->eventId), array_merge([
                'subject' => 'Doors open at 7',
                'message' => '<p>See you there</p>',
                'is_test' => false,
                'send_copy_to_current_user' => false,
                'attendee_ids' => [],
                'product_ids' => [],
                'order_statuses' => ['COMPLETED'],
            ], $overrides));
    }
}
