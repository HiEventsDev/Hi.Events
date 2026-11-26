<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Actions\Messages;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\MessagingTierViolationEnum;
use HiEvents\Exceptions\MessagingTierLimitExceededException;
use HiEvents\Models\User;
use HiEvents\Services\Application\Handlers\Message\SendMessageHandler;
use HiEvents\Services\Domain\Message\DTO\MessagingTierViolationDTO;
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

    public function test_sends_are_rate_limited_per_member(): void
    {
        foreach (range(1, 60) as $ignored) {
            $this->sendMessage(['message_type' => null])->assertStatus(422);
        }

        $this->sendMessage(['message_type' => null])->assertStatus(429);
    }

    public function test_a_tier_limit_violation_is_identified_by_its_error_code(): void
    {
        $this->mock(SendMessageHandler::class)
            ->shouldReceive('handle')
            ->andThrow(new MessagingTierLimitExceededException(new MessagingTierViolationDTO(
                accountId: $this->accountId,
                tierName: 'Untrusted',
                violations: [MessagingTierViolationEnum::MESSAGE_LIMIT_EXCEEDED],
            )));

        $this->sendMessage(['message_type' => 'ALL_ATTENDEES'])
            ->assertStatus(429)
            ->assertJsonPath('error_code', MessagingTierLimitExceededException::ERROR_CODE);
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
