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

class PreviewMessageActionTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private string $authToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('event_settings')->insert([
            'event_id' => $this->eventId,
            'support_email' => 'support@acme.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));
    }

    public function test_it_renders_the_purified_message_in_the_email_layout_without_storing_it(): void
    {
        $response = $this->preview([
            'subject' => ' Doors open at 7 ',
            'message' => '<p>See you there</p><script>alert(1)</script>',
        ])->assertOk();

        $html = $response->json('data.html');

        $this->assertStringContainsString('See you there</p>', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringContainsString('<td class="header"', $html);
        $this->assertSame('Doors open at 7', $response->json('data.subject'));
        $this->assertSame(config('mail.from.name'), $response->json('data.from_name'));
        $this->assertSame('support@acme.test', $response->json('data.reply_to'));
        $this->assertSame(0, DB::table('messages')->where('event_id', $this->eventId)->count());
    }

    public function test_an_empty_draft_still_previews(): void
    {
        $this->preview([])->assertOk()->assertJsonPath('data.subject', '');
    }

    public function test_an_over_long_message_is_rejected(): void
    {
        $this->preview(['message' => str_repeat('a', 20001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_another_accounts_event_cannot_be_previewed(): void
    {
        $ownEventId = $this->eventId;
        $this->insertAccountAndOrganizer();
        $otherEventId = $this->insertEvent(EventType::SINGLE->name);

        $this->assertNotSame($ownEventId, $otherEventId);

        $this->withHeader('Authorization', 'Bearer '.$this->authToken)
            ->postJson(sprintf('/events/%d/messages/preview', $otherEventId), ['subject' => 'Hi', 'message' => '<p>Hi</p>'])
            ->assertForbidden();
    }

    private function preview(array $payload)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->authToken)
            ->postJson(sprintf('/events/%d/messages/preview', $this->eventId), $payload);
    }
}
