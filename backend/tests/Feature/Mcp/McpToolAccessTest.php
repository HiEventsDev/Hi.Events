<?php

namespace Tests\Feature\Mcp;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\DomainObjects\Enums\OrganizerReportTypes;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Enums\ReportTypes;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\CallsMcpTools;
use Tests\Concerns\UsesApiKeys;
use Tests\TestCase;

class McpToolAccessTest extends TestCase
{
    use ActsAsAccountUsers;
    use CallsMcpTools;
    use DatabaseTransactions;
    use UsesApiKeys;

    private User $owner;

    private int $accountId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId);
    }

    public function test_a_draft_event_with_a_ticket_can_be_set_up_end_to_end(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $eventId = $this->callTool($apiKey, 'create_event', [
            'organizer_id' => $this->createOrganizerWithTool($apiKey),
            'title' => 'Set up over MCP',
            'start_date' => '2027-03-01 19:00',
            'end_date' => '2027-03-01 22:00',
        ])->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.event.status', 'DRAFT')
            ->json('result.structuredContent.event.id');

        $ticket = $this->callTool($apiKey, 'create_ticket', [
            'event_id' => $eventId,
            'title' => 'General admission',
            'type' => 'PAID',
            'price' => 20,
            'quantity_available' => 50,
        ])->assertJsonPath('result.isError', false)->json('result.structuredContent.ticket');

        $this->assertSame(20, $ticket['prices'][0]['price']);
        $this->assertDatabaseHas('event_occurrences', ['event_id' => $eventId, 'start_date' => '2027-03-01 19:00:00']);
    }

    public function test_updating_a_ticket_keeps_settings_the_tool_does_not_expose(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        $eventId = $this->callTool($apiKey, 'create_event', [
            'organizer_id' => $this->createOrganizerWithTool($apiKey),
            'title' => 'Keeps settings',
            'start_date' => '2027-03-01 19:00',
        ])->json('result.structuredContent.event.id');
        $ticketId = $this->callTool($apiKey, 'create_ticket', [
            'event_id' => $eventId, 'title' => 'GA', 'type' => 'PAID', 'price' => 20,
        ])->json('result.structuredContent.ticket.id');
        DB::table('products')->where('id', $ticketId)->update(['hide_when_sold_out' => true, 'is_hidden_without_promo_code' => true]);

        $this->callTool($apiKey, 'update_ticket', ['event_id' => $eventId, 'ticket_id' => $ticketId, 'price' => 25])
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.ticket.prices.0.price', 25);

        $this->assertDatabaseHas('products', ['id' => $ticketId, 'hide_when_sold_out' => true, 'is_hidden_without_promo_code' => true]);
    }

    public function test_a_viewer_can_read_but_not_write(): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        $apiKey = $this->createApiKey($viewer, $this->accountId);

        $this->callTool($apiKey, 'list_organizers')->assertJsonPath('result.isError', false);

        $tools = collect($this->mcp($apiKey, 'tools/list')->assertOk()->json('result.tools'));
        $this->assertTrue($tools->every(fn (array $tool) => $tool['annotations']['readOnlyHint'] ?? false));
        $this->assertContains('list_organizers', $tools->pluck('name'));

        $this->callTool($apiKey, 'create_event', ['organizer_id' => $this->organizerId, 'title' => 'Viewer event', 'start_date' => '2027-03-01 19:00'])
            ->assertJsonStructure(['error' => ['code', 'message']]);

        $this->assertDatabaseMissing('events', ['title' => 'Viewer event']);
    }

    public function test_stats_hide_money_from_members_without_financial_access(): void
    {
        $eventId = $this->createEvent($this->accountId, $this->organizerId);
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, [Permission::VIEW_FINANCIALS->value => false]);
        $apiKey = $this->createApiKey($member, $this->accountId);

        $stats = $this->callTool($apiKey, 'get_event_stats', ['event_id' => $eventId])
            ->assertJsonPath('result.isError', false)
            ->json('result.structuredContent.stats');

        $this->assertArrayHasKey('total_orders', $stats);
        $this->assertArrayNotHasKey('total_gross_sales', $stats);

        $organizerStats = $this->callTool($apiKey, 'get_organizer_stats', [
            'organizer_id' => $this->organizerId, 'from' => now()->subDays(2)->toDateString(), 'include_daily' => true,
        ])
            ->assertJsonPath('result.isError', false)
            ->json('result.structuredContent');

        $this->assertArrayHasKey('total_orders', $organizerStats['stats']);
        $this->assertArrayNotHasKey('total_gross_sales', $organizerStats['stats']);
        $this->assertCount(3, $organizerStats['daily']);
        $this->assertArrayNotHasKey('total_sales_gross', $organizerStats['daily'][0]);
    }

    public function test_every_report_type_runs_through_the_api(): void
    {
        $eventId = $this->createEvent($this->accountId, $this->organizerId);
        $dateId = DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $eventId,
            'start_date' => '2026-12-12 20:00:00',
            'end_date' => '2026-12-12 23:00:00',
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        $allTime = [ReportTypes::OCCURRENCE_SUMMARY->value, ReportTypes::SEATING_SALES->value, OrganizerReportTypes::EVENTS_PERFORMANCE->value, OrganizerReportTypes::CHECK_IN_SUMMARY->value];

        foreach (ReportTypes::valuesArray() as $reportType) {
            $range = in_array($reportType, $allTime, true) ? [] : ['from' => '2026-10-01', 'to' => '2026-10-01'];
            $dateFilter = $reportType === ReportTypes::OCCURRENCE_SUMMARY->value ? [] : ['date_id' => $dateId];

            $this->callTool($apiKey, 'get_event_report', ['event_id' => $eventId, 'report_type' => $reportType, ...$range, ...$dateFilter])
                ->assertJsonPath('result.isError', false)
                ->assertJsonPath('result.structuredContent.period', $range === [] ? 'all_time' : ['from' => '2026-10-01', 'to' => '2026-10-01']);
        }

        foreach (OrganizerReportTypes::valuesArray() as $reportType) {
            $this->callTool($apiKey, 'get_organizer_report', ['organizer_id' => $this->organizerId, 'report_type' => $reportType])
                ->assertJsonPath('result.isError', false)
                ->assertJsonPath('result.structuredContent.report_type', $reportType);
        }

        $this->callTool($apiKey, 'get_event_report', ['event_id' => $eventId, 'report_type' => ReportTypes::OCCURRENCE_SUMMARY->value])
            ->assertJsonPath('result.structuredContent.rows.0.start_date', '2026-12-12T20:00:00+00:00');
    }

    public function test_financial_reports_need_financial_access(): void
    {
        $eventId = $this->createEvent($this->accountId, $this->organizerId);
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, [Permission::VIEW_FINANCIALS->value => false]);
        $apiKey = $this->createApiKey($member, $this->accountId);

        $this->callTool($apiKey, 'get_event_report', ['event_id' => $eventId, 'report_type' => ReportTypes::PRODUCT_SALES->value])
            ->assertJsonPath('result.isError', true);
        $this->callTool($apiKey, 'get_organizer_report', ['organizer_id' => $this->organizerId, 'report_type' => OrganizerReportTypes::REVENUE_SUMMARY->value])
            ->assertJsonPath('result.isError', true);
        $this->callTool($apiKey, 'get_organizer_report', ['organizer_id' => $this->organizerId, 'report_type' => OrganizerReportTypes::CHECK_IN_SUMMARY->value])
            ->assertJsonPath('result.isError', false);
    }

    public function test_sales_tools_respect_read_only_access_and_financial_permissions(): void
    {
        $owner = $this->createApiKey($this->owner, $this->accountId);
        $eventId = $this->createEventWithTool($owner, $this->createOrganizerWithTool($owner));
        $ticketId = $this->callTool($owner, 'create_ticket', ['event_id' => $eventId, 'title' => 'GA', 'type' => 'PAID', 'price' => 30])
            ->json('result.structuredContent.ticket.id');
        $attendee = $this->callTool($owner, 'add_attendee', [
            'event_id' => $eventId, 'ticket_id' => $ticketId, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'send_ticket' => false,
        ])->assertJsonPath('result.isError', false)->json('result.structuredContent.attendee');

        $readOnly = $this->createApiKey($this->owner, $this->accountId, ApiKeyAccess::READ_ONLY);
        $this->callTool($readOnly, 'get_attendee', ['event_id' => $eventId, 'attendee_id' => $attendee['id']])->assertJsonPath('result.isError', false);
        $this->callTool($readOnly, 'check_in_attendee', ['event_id' => $eventId, 'attendee_id' => $attendee['id']])->assertJsonStructure(['error' => ['code', 'message']]);
        $this->assertSame(0, DB::table('attendee_check_ins')->where('attendee_id', $attendee['id'])->count());

        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, [Permission::VIEW_FINANCIALS->value => false]);
        $order = $this->callTool($this->createApiKey($member, $this->accountId), 'get_order', ['event_id' => $eventId, 'order_id' => $attendee['order_id']])
            ->assertJsonPath('result.isError', false)
            ->json('result.structuredContent.order');

        $this->assertSame('jane@example.com', $order['email']);
        $this->assertArrayNotHasKey('total', $order);
        $this->assertArrayNotHasKey('price', $order['items'][0]);
    }

    public function test_organizer_stats_refuse_another_accounts_organizer(): void
    {
        [, $otherAccountId] = $this->createAccountOwner();
        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $this->callTool($apiKey, 'get_organizer_stats', ['organizer_id' => $this->createOrganizer($otherAccountId)])
            ->assertJsonPath('result.isError', true);
    }

    public function test_tools_cannot_reach_another_accounts_records(): void
    {
        [, $otherAccountId] = $this->createAccountOwner();
        $otherOrganizerId = $this->createOrganizer($otherAccountId);
        $otherEventId = $this->createEvent($otherAccountId, $otherOrganizerId, 'Not yours');
        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $this->callTool($apiKey, 'create_ticket', ['event_id' => $otherEventId, 'title' => 'Sneaky', 'type' => 'FREE'])
            ->assertJsonPath('result.isError', true);
        $this->callTool($apiKey, 'create_event', ['organizer_id' => $otherOrganizerId, 'title' => 'Sneaky event', 'start_date' => '2027-03-01 19:00'])
            ->assertJsonPath('result.isError', true);
        $this->callTool($apiKey, 'publish_event', ['event_id' => $otherEventId])
            ->assertJsonPath('result.isError', true);

        $this->assertDatabaseMissing('products', ['title' => 'Sneaky']);
        $this->assertDatabaseMissing('events', ['title' => 'Sneaky event']);
        $this->assertDatabaseHas('events', ['id' => $otherEventId, 'status' => null]);
    }

    public function test_settings_tool_cannot_change_payment_settings(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        $eventId = $this->callTool($apiKey, 'create_event', [
            'organizer_id' => $this->createOrganizerWithTool($apiKey),
            'title' => 'Payments stay put',
            'start_date' => '2027-03-01 19:00',
        ])->json('result.structuredContent.event.id');
        $before = DB::table('event_settings')->where('event_id', $eventId)->value('payment_providers');

        $this->callTool($apiKey, 'update_event_settings', [
            'event_id' => $eventId,
            'support_email' => 'help@example.com',
            'payment_providers' => ['OFFLINE'],
        ])->assertJsonPath('result.isError', false);

        $this->assertDatabaseHas('event_settings', ['event_id' => $eventId, 'support_email' => 'help@example.com']);
        $this->assertSame($before, DB::table('event_settings')->where('event_id', $eventId)->value('payment_providers'));
    }
}
