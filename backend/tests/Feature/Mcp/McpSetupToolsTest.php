<?php

namespace Tests\Feature\Mcp;

use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\CallsMcpTools;
use Tests\Concerns\UsesApiKeys;
use Tests\TestCase;

class McpSetupToolsTest extends TestCase
{
    use ActsAsAccountUsers;
    use CallsMcpTools;
    use DatabaseTransactions;
    use UsesApiKeys;

    private User $owner;

    private int $accountId;

    private string $apiKey;

    private int $organizerId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->apiKey = $this->createApiKey($this->owner, $this->accountId);
        $this->organizerId = $this->createOrganizerWithTool($this->apiKey);
        $this->eventId = $this->createEventWithTool($this->apiKey, $this->organizerId);
    }

    public function test_a_follow_up_question_survives_renaming_its_parent_option(): void
    {
        $parentId = $this->callTool($this->apiKey, 'create_question', [
            'event_id' => $this->eventId, 'title' => 'Dietary requirements?', 'type' => 'RADIO', 'options' => ['No', 'Yes'], 'required' => true,
        ])->assertJsonPath('result.isError', false)->json('result.structuredContent.question.id');

        $followUpId = $this->callTool($this->apiKey, 'create_question', [
            'event_id' => $this->eventId, 'title' => 'Which ones?', 'type' => 'MULTI_LINE_TEXT',
            'condition_question_id' => $parentId, 'condition_values' => ['Yes'],
        ])->assertJsonPath('result.isError', false)->json('result.structuredContent.question.id');

        $this->callTool($this->apiKey, 'update_question', [
            'event_id' => $this->eventId, 'question_id' => $parentId,
            'options' => ['No', 'Yes, please'], 'option_renames' => [['from' => 'Yes', 'to' => 'Yes, please']],
        ])->assertJsonPath('result.isError', false);

        $questions = collect($this->callTool($this->apiKey, 'list_questions', ['event_id' => $this->eventId])->json('result.structuredContent.questions'))->keyBy('id');

        $this->assertSame(['Yes, please'], $questions[$followUpId]['condition_values']);
        $this->assertTrue($questions[$parentId]['required']);
    }

    public function test_an_addon_is_offered_with_its_tickets_and_survives_a_ticket_edit(): void
    {
        $ticketIds = collect(['GA', 'VIP'])->map(fn (string $title): int => $this->callTool($this->apiKey, 'create_ticket', [
            'event_id' => $this->eventId, 'title' => $title, 'type' => 'PAID', 'price' => 20,
        ])->assertJsonPath('result.isError', false)->json('result.structuredContent.ticket.id'))->all();

        $addonId = $this->callTool($this->apiKey, 'create_addon', [
            'event_id' => $this->eventId, 'ticket_ids' => $ticketIds, 'title' => 'Parking', 'type' => 'PAID', 'price' => 10,
        ])
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.addon.is_addon_only', true)
            ->json('result.structuredContent.addon.id');

        $this->assertDatabaseHas('products', ['id' => $addonId, 'is_addon_only' => true, 'product_type' => 'GENERAL']);
        $this->assertSame($ticketIds, DB::table('product_addons')->where('addon_product_id', $addonId)->orderBy('product_id')->pluck('product_id')->all());

        $this->callTool($this->apiKey, 'update_ticket', ['event_id' => $this->eventId, 'ticket_id' => $ticketIds[0], 'title' => 'General admission'])
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.ticket.addon_ids', [$addonId]);
    }

    public function test_a_failed_link_deletes_the_addon_and_unlinks_it_from_tickets_already_done(): void
    {
        $ticketIds = collect(['GA', 'VIP'])->map(fn (string $title): int => $this->callTool($this->apiKey, 'create_ticket', [
            'event_id' => $this->eventId, 'title' => $title, 'type' => 'FREE',
        ])->json('result.structuredContent.ticket.id'))->all();
        DB::table('products')->where('id', $ticketIds[1])->update(['title' => '']);

        $this->callTool($this->apiKey, 'create_addon', [
            'event_id' => $this->eventId, 'ticket_ids' => $ticketIds, 'title' => 'Parking', 'type' => 'FREE',
        ])->assertJsonPath('result.isError', true);

        $this->assertSame(0, DB::table('products')->where('event_id', $this->eventId)->where('title', 'Parking')->whereNull('deleted_at')->count());
        $this->assertSame(0, DB::table('product_addons')->whereIn('product_id', $ticketIds)->count());
    }

    public function test_an_addon_cannot_be_attached_to_another_events_ticket(): void
    {
        $otherEventId = $this->createEventWithTool($this->apiKey, $this->organizerId, 'Other event');
        $otherTicketId = $this->callTool($this->apiKey, 'create_ticket', ['event_id' => $otherEventId, 'title' => 'GA', 'type' => 'FREE'])
            ->json('result.structuredContent.ticket.id');

        $this->callTool($this->apiKey, 'create_addon', [
            'event_id' => $this->eventId, 'ticket_ids' => [$otherTicketId], 'title' => 'Parking', 'type' => 'FREE',
        ])->assertJsonPath('result.isError', true);

        $this->assertDatabaseMissing('products', ['event_id' => $this->eventId, 'title' => 'Parking']);
    }

    public function test_a_guest_list_attendee_can_be_found_read_checked_in_and_resent_their_ticket(): void
    {
        $this->useRealMailer();
        $ticketId = $this->callTool($this->apiKey, 'create_ticket', ['event_id' => $this->eventId, 'title' => 'GA', 'type' => 'PAID', 'price' => 25])
            ->json('result.structuredContent.ticket.id');

        $attendee = $this->callTool($this->apiKey, 'add_attendee', [
            'event_id' => $this->eventId, 'ticket_id' => $ticketId, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
        ])->assertJsonPath('result.isError', false)->json('result.structuredContent.attendee');

        $this->assertSame('0.00', DB::table('orders')->where('id', $attendee['order_id'])->value('total_gross'));
        $mailsAfterAdding = $this->mailsTo('jane@example.com');
        $this->assertGreaterThan(0, $mailsAfterAdding);

        $questionId = $this->callTool($this->apiKey, 'create_question', [
            'event_id' => $this->eventId, 'title' => 'Dietary needs?', 'type' => 'SINGLE_LINE_TEXT', 'belongs_to' => 'PRODUCT', 'product_ids' => [$ticketId],
        ])->json('result.structuredContent.question.id');
        DB::table('question_answers')->insert([
            'question_id' => $questionId, 'order_id' => $attendee['order_id'], 'attendee_id' => $attendee['id'], 'product_id' => $ticketId,
            'answer' => json_encode('Vegan'), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->callTool($this->apiKey, 'list_attendees', ['event_id' => $this->eventId, 'search' => 'jane@'])
            ->assertJsonPath('result.structuredContent.total', 1)
            ->assertJsonPath('result.structuredContent.attendees.0.checked_in', false);

        $this->callTool($this->apiKey, 'get_attendee', ['event_id' => $this->eventId, 'attendee_id' => $attendee['id']])
            ->assertJsonPath('result.structuredContent.attendee.answers.0.question', 'Dietary needs?')
            ->assertJsonPath('result.structuredContent.attendee.answers.0.answer', 'Vegan');

        $this->callTool($this->apiKey, 'check_in_attendee', ['event_id' => $this->eventId, 'attendee_id' => $attendee['id']])
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.already_checked_in', false);
        $this->callTool($this->apiKey, 'check_in_attendee', ['event_id' => $this->eventId, 'attendee_id' => $attendee['id']])
            ->assertJsonPath('result.structuredContent.already_checked_in', true);
        $this->assertSame(1, DB::table('attendee_check_ins')->where('attendee_id', $attendee['id'])->whereNull('deleted_at')->count());

        $this->callTool($this->apiKey, 'resend_ticket', ['event_id' => $this->eventId, 'attendee_id' => $attendee['id']])
            ->assertJsonPath('result.isError', false);
        $this->callTool($this->apiKey, 'resend_ticket', ['event_id' => $this->eventId, 'attendee_id' => $attendee['id']])
            ->assertJsonPath('result.isError', true);
        $this->assertSame($mailsAfterAdding + 1, $this->mailsTo('jane@example.com'));

        $this->callTool($this->apiKey, 'get_order', ['event_id' => $this->eventId, 'order_id' => $attendee['order_id']])
            ->assertJsonPath('result.structuredContent.order.status', 'COMPLETED')
            ->assertJsonPath('result.structuredContent.order.attendees.0.id', $attendee['id'])
            ->assertJsonPath('result.structuredContent.order.answers.0.attendee_name', 'Jane Doe');

        $this->callTool($this->apiKey, 'list_orders', ['organizer_id' => $this->organizerId, 'search' => 'jane@'])
            ->assertJsonPath('result.structuredContent.orders.0.id', $attendee['order_id']);
        $this->callTool($this->apiKey, 'list_orders', ['organizer_id' => $this->organizerId, 'status' => 'COMPLETED'])
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.orders.0.id', $attendee['order_id']);
        $this->callTool($this->apiKey, 'list_orders', ['event_id' => $this->eventId, 'status' => 'CANCELLED'])
            ->assertJsonPath('result.structuredContent.total', 0);

        $this->callTool($this->apiKey, 'resend_order_confirmation', ['event_id' => $this->eventId, 'order_id' => $attendee['order_id']])
            ->assertJsonPath('result.isError', false);
        $this->assertSame($mailsAfterAdding + 2, $this->mailsTo('jane@example.com'));
    }

    public function test_attendees_and_orders_of_another_event_are_not_reachable(): void
    {
        $this->useRealMailer();
        $otherEventId = $this->createEventWithTool($this->apiKey, $this->organizerId, 'Other event');
        $ticketId = $this->callTool($this->apiKey, 'create_ticket', ['event_id' => $otherEventId, 'title' => 'GA', 'type' => 'FREE'])
            ->json('result.structuredContent.ticket.id');
        $attendee = $this->callTool($this->apiKey, 'add_attendee', [
            'event_id' => $otherEventId, 'ticket_id' => $ticketId, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'send_ticket' => false,
        ])->json('result.structuredContent.attendee');

        foreach ([
            ['get_attendee', ['attendee_id' => $attendee['id']]],
            ['check_in_attendee', ['attendee_id' => $attendee['id']]],
            ['resend_ticket', ['attendee_id' => $attendee['id']]],
            ['get_order', ['order_id' => $attendee['order_id']]],
            ['resend_order_confirmation', ['order_id' => $attendee['order_id']]],
        ] as [$tool, $arguments]) {
            $this->callTool($this->apiKey, $tool, ['event_id' => $this->eventId, ...$arguments])->assertJsonPath('result.isError', true);
        }

        $this->assertSame(0, DB::table('attendee_check_ins')->where('attendee_id', $attendee['id'])->count());
    }

    public function test_ticket_design_updates_keep_earlier_choices(): void
    {
        $this->callTool($this->apiKey, 'update_ticket_design', ['event_id' => $this->eventId, 'enabled' => true, 'accent_color' => '#6D28D9'])
            ->assertJsonPath('result.isError', false);
        $this->callTool($this->apiKey, 'update_ticket_design', ['event_id' => $this->eventId, 'footer_text' => 'Bring ID'])
            ->assertJsonPath('result.isError', false);

        $design = json_decode(DB::table('event_settings')->where('event_id', $this->eventId)->value('ticket_design_settings'), true);

        $this->assertSame('#6D28D9', $design['accent_color']);
        $this->assertSame('Bring ID', $design['footer_text']);
        $this->assertTrue($design['enabled']);
    }

    public function test_tickets_promo_codes_and_shared_capacity_link_up(): void
    {
        $categoryId = $this->callTool($this->apiKey, 'create_ticket_category', ['event_id' => $this->eventId, 'name' => 'Passes'])
            ->json('result.structuredContent.category.id');
        $ticketId = $this->callTool($this->apiKey, 'create_ticket', [
            'event_id' => $this->eventId, 'title' => 'Day pass', 'type' => 'PAID', 'price' => 45, 'category_id' => $categoryId,
        ])->assertJsonPath('result.isError', false)->json('result.structuredContent.ticket.id');

        $this->callTool($this->apiKey, 'create_shared_capacity', ['event_id' => $this->eventId, 'name' => 'Site', 'capacity' => 500, 'product_ids' => [$ticketId]])
            ->assertJsonPath('result.isError', false);
        $this->callTool($this->apiKey, 'create_promo_code', [
            'event_id' => $this->eventId, 'code' => 'EARLY20', 'discount_type' => 'PERCENTAGE', 'discount' => 20, 'applicable_product_ids' => [$ticketId],
        ])->assertJsonPath('result.structuredContent.promo_code.code', 'early20');

        $this->assertDatabaseHas('products', ['id' => $ticketId, 'product_category_id' => $categoryId]);
        $this->assertDatabaseHas('promo_codes', ['event_id' => $this->eventId, 'code' => 'early20']);
        $this->assertSame(1, DB::table('capacity_assignments')->where('event_id', $this->eventId)->count());
    }

    public function test_a_lineup_with_a_new_performer_and_a_slot(): void
    {
        $spaceId = $this->callTool($this->apiKey, 'add_lineup_space', ['event_id' => $this->eventId, 'name' => 'Main stage'])
            ->json('result.structuredContent.space.id');
        $entryId = $this->callTool($this->apiKey, 'add_lineup_performer', ['event_id' => $this->eventId, 'name' => 'The Ensemble', 'billing_tier' => 'HEADLINER'])
            ->assertJsonPath('result.isError', false)->json('result.structuredContent.performer.id');

        $this->callTool($this->apiKey, 'add_schedule_item', [
            'event_id' => $this->eventId, 'starts_at' => '2027-03-01 20:00', 'ends_at' => '2027-03-01 21:00',
            'event_space_id' => $spaceId, 'lineup_entry_ids' => [$entryId],
        ])->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.schedule_item.starts_at', '2027-03-01T20:00:00+00:00');

        $this->assertDatabaseHas('profiles', ['organizer_id' => $this->organizerId, 'name' => 'The Ensemble']);
    }

    public function test_ids_from_another_account_are_refused(): void
    {
        [$otherOwner, $otherAccountId] = $this->createAccountOwner();
        $otherKey = $this->createApiKey($otherOwner, $otherAccountId);
        $otherOrganizerId = $this->createOrganizerWithTool($otherKey);
        $otherEventId = $this->createEventWithTool($otherKey, $otherOrganizerId, 'Theirs');
        $otherCategoryId = $this->callTool($otherKey, 'list_ticket_categories', ['event_id' => $otherEventId])->json('result.structuredContent.categories.0.id');
        $otherTicketId = $this->callTool($otherKey, 'create_ticket', ['event_id' => $otherEventId, 'title' => 'Their ticket', 'type' => 'FREE'])
            ->json('result.structuredContent.ticket.id');
        $otherProfileEntry = $this->callTool($otherKey, 'add_lineup_performer', ['event_id' => $otherEventId, 'name' => 'Their act'])
            ->json('result.structuredContent.performer');

        $refused = [
            ['create_ticket', ['event_id' => $this->eventId, 'title' => 'Sneaky', 'type' => 'FREE', 'category_id' => $otherCategoryId]],
            ['create_question', ['event_id' => $this->eventId, 'title' => 'Sneaky?', 'type' => 'SINGLE_LINE_TEXT', 'belongs_to' => 'PRODUCT', 'product_ids' => [$otherTicketId]]],
            ['create_promo_code', ['event_id' => $this->eventId, 'code' => 'SNEAKY', 'discount_type' => 'NONE', 'applicable_product_ids' => [$otherTicketId]]],
            ['add_lineup_performer', ['event_id' => $this->eventId, 'profile_id' => $otherProfileEntry['profile_id']]],
            ['create_widget', ['event_id' => $otherEventId, 'type' => 'TICKETS', 'name' => 'Sneaky widget']],
            ['update_event_page_theme', ['event_id' => $otherEventId, 'accent' => '#000000']],
        ];

        foreach ($refused as [$tool, $arguments]) {
            $this->callTool($this->apiKey, $tool, $arguments)->assertJsonPath('result.isError', true);
        }

        $this->assertDatabaseMissing('products', ['title' => 'Sneaky']);
        $this->assertDatabaseMissing('questions', ['title' => 'Sneaky?']);
        $this->assertDatabaseMissing('promo_codes', ['code' => 'sneaky']);
        $this->assertDatabaseMissing('widgets', ['name' => 'Sneaky widget']);
        $this->assertSame(0, DB::table('event_lineup_entries')->where('event_id', $this->eventId)->count());
    }

    private function useRealMailer(): void
    {
        $this->app->forgetInstance('mail.manager');
        $this->app->forgetInstance('mailer');
        Mail::clearResolvedInstances();
    }

    private function mailsTo(string $email): int
    {
        return Mail::mailer()->getSymfonyTransport()->messages()
            ->filter(static fn (SentMessage $message): bool => collect($message->getEnvelope()->getRecipients())->contains(fn ($address) => $address->getAddress() === $email))
            ->count();
    }
}
