<?php

namespace Tests\Feature\Mcp;

use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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
}
