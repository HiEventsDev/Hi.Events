<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Attendees\CreateAttendeeAction;
use HiEvents\Http\Actions\Attendees\GetAttendeeAction;
use HiEvents\Http\Actions\Attendees\GetAttendeesAction;
use HiEvents\Http\Actions\Attendees\ResendAttendeeTicketAction;
use HiEvents\Http\Actions\CheckInLists\GetCheckInListsAction;
use HiEvents\Http\Actions\CheckInLists\Public\CreateAttendeeCheckInPublicAction;
use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\Orders\GetOrderAction;
use HiEvents\Http\Actions\Orders\GetOrdersAction;
use HiEvents\Http\Actions\Orders\ResendOrderConfirmationAction;
use HiEvents\Http\Actions\Organizers\GetOrganizerAction;
use HiEvents\Http\Actions\Organizers\Orders\GetOrganizerOrdersAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use HiEvents\Mcp\Tools\AddAttendeeTool;
use HiEvents\Mcp\Tools\CheckInAttendeeTool;
use HiEvents\Mcp\Tools\GetAttendeeTool;
use HiEvents\Mcp\Tools\GetOrderTool;
use HiEvents\Mcp\Tools\ListAttendeesTool;
use HiEvents\Mcp\Tools\ListOrdersTool;
use HiEvents\Mcp\Tools\ResendOrderConfirmationTool;
use HiEvents\Mcp\Tools\ResendTicketTool;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Mcp\Request;
use Mockery;

class SalesToolsTest extends McpToolTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
    }

    public function test_attendees_are_listed_with_search_and_filters(): void
    {
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetAttendeesAction::class, ['event_id' => 12], [
                'per_page' => ListAttendeesTool::PER_PAGE,
                'page' => 2,
                'query' => 'jane',
                'filter_fields' => ['status' => ['eq' => 'ACTIVE'], 'product_id' => ['eq' => 5]],
            ])
            ->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_OK, body: [
                'data' => [$this->attendee(['check_ins' => [['created_at' => '2026-12-12 01:30:00']]])],
                'meta' => ['current_page' => 2, 'last_page' => 3, 'total' => 120],
            ]));

        $result = $this->structured((new ListAttendeesTool($this->api))->handle(new Request([
            'event_id' => 12, 'search' => 'jane', 'status' => 'ACTIVE', 'ticket_id' => 5, 'page' => 2,
        ])));

        $this->assertSame(120, $result['total']);
        $this->assertSame('jane@example.com', $result['attendees'][0]['email']);
        $this->assertTrue($result['attendees'][0]['checked_in']);
        $this->assertSame('2026-12-11T20:30:00-05:00', $result['attendees'][0]['checked_in_at']);
        $this->assertArrayNotHasKey('answers', $result['attendees'][0]);
    }

    public function test_one_attendee_comes_with_their_registration_answers(): void
    {
        $this->api->shouldReceive('get')->once()->with(GetAttendeeAction::class, ['event_id' => 12, 'attendee_id' => 40])->andReturn($this->ok($this->attendee([
            'product' => ['title' => 'VIP'],
            'check_ins' => [],
            'question_answers' => [[
                'question_id' => 3, 'title' => 'Dietary needs?', 'text_answer' => 'Vegan', 'belongs_to' => 'PRODUCT',
                'product_title' => 'VIP', 'attendee_id' => 40, 'first_name' => 'Jane', 'last_name' => 'Doe', 'answer' => ['Vegan'],
            ]],
        ])));

        $attendee = $this->structured((new GetAttendeeTool($this->api))->handle(new Request(['event_id' => 12, 'attendee_id' => 40])))['attendee'];

        $this->assertSame('VIP', $attendee['ticket_title']);
        $this->assertFalse($attendee['checked_in']);
        $this->assertSame([[
            'question_id' => 3, 'question' => 'Dietary needs?', 'answer' => 'Vegan', 'applies_to' => 'PRODUCT',
            'ticket_title' => 'VIP', 'attendee_id' => 40, 'attendee_name' => 'Jane Doe',
        ]], $attendee['answers']);
    }

    public function test_orders_list_for_an_event_without_attendees_or_answers(): void
    {
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetOrdersAction::class, ['event_id' => 12], ['per_page' => ListOrdersTool::PER_PAGE, 'page' => 1, 'filter_fields' => ['status' => ['eq' => 'AWAITING_OFFLINE_PAYMENT']]])
            ->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_OK, body: [
                'data' => [$this->order(['attendees' => [$this->attendee()], 'question_answers' => []])],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 1],
            ]));

        $order = $this->structured((new ListOrdersTool($this->api))->handle(new Request(['event_id' => 12, 'status' => 'AWAITING_OFFLINE_PAYMENT'])))['orders'][0];

        $this->assertSame('O-ABC123', $order['reference']);
        $this->assertSame(40.0, $order['total']);
        $this->assertSame([['ticket_id' => 5, 'title' => 'VIP', 'quantity' => 2, 'price' => 20.0]], $order['items']);
        $this->assertArrayNotHasKey('attendees', $order);
        $this->assertArrayNotHasKey('answers', $order);
    }

    public function test_orders_list_across_an_organizer_in_its_timezone(): void
    {
        $this->api->shouldReceive('get')->with(GetOrganizerAction::class, ['organizer_id' => 3])->andReturn($this->ok(['id' => 3, 'timezone' => 'Europe/Dublin']));
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetOrganizerOrdersAction::class, ['organizer_id' => 3], ['per_page' => ListOrdersTool::PER_PAGE, 'page' => 1, 'query' => 'doe'])
            ->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_OK, body: ['data' => [$this->order()], 'meta' => []]));

        $order = $this->structured((new ListOrdersTool($this->api))->handle(new Request(['organizer_id' => 3, 'search' => 'doe'])))['orders'][0];

        $this->assertSame('2026-10-01T13:00:00+01:00', $order['created_at']);
        $this->assertSame(12, $order['event_id']);
    }

    public function test_orders_need_exactly_one_of_event_or_organizer(): void
    {
        $this->api->shouldNotReceive('get')->with(GetOrdersAction::class, Mockery::any(), Mockery::any());

        $tool = new ListOrdersTool($this->api);

        $this->assertSame('Give an event_id or an organizer_id.', $this->errorText($tool->handle(new Request([]))));
        $this->assertSame('Give an event_id or an organizer_id.', $this->errorText($tool->handle(new Request(['event_id' => 12, 'organizer_id' => 3]))));
    }

    public function test_one_order_comes_with_attendees_and_every_answer(): void
    {
        $this->api->shouldReceive('get')->once()->with(GetOrderAction::class, ['event_id' => 12, 'order_id' => 7])->andReturn($this->ok($this->order([
            'attendees' => [$this->attendee()],
            'question_answers' => [
                ['question_id' => 1, 'title' => 'Company?', 'text_answer' => 'Acme', 'belongs_to' => 'ORDER'],
                ['question_id' => 3, 'title' => 'Dietary needs?', 'text_answer' => 'Vegan', 'belongs_to' => 'PRODUCT', 'attendee_id' => 40, 'first_name' => 'Jane', 'last_name' => 'Doe'],
            ],
        ])));

        $order = $this->structured((new GetOrderTool($this->api))->handle(new Request(['event_id' => 12, 'order_id' => 7])))['order'];

        $this->assertSame(40, $order['attendees'][0]['id']);
        $this->assertSame(['question_id' => 1, 'question' => 'Company?', 'answer' => 'Acme', 'applies_to' => 'ORDER'], $order['answers'][0]);
        $this->assertSame('Jane Doe', $order['answers'][1]['attendee_name']);
    }

    public function test_an_order_without_financial_access_has_no_amounts(): void
    {
        $order = $this->order();
        unset($order['total_gross'], $order['order_items'][0]['price']);
        $this->api->shouldReceive('get')->with(GetOrderAction::class, ['event_id' => 12, 'order_id' => 7])->andReturn($this->ok($order));

        $result = $this->structured((new GetOrderTool($this->api))->handle(new Request(['event_id' => 12, 'order_id' => 7])))['order'];

        $this->assertArrayNotHasKey('total', $result);
        $this->assertArrayNotHasKey('price', $result['items'][0]);
    }

    public function test_check_in_uses_the_date_specific_list_and_only_the_plain_check_in_action(): void
    {
        $this->api->shouldReceive('get')->with(GetAttendeeAction::class, ['event_id' => 12, 'attendee_id' => 40])->andReturn($this->ok($this->attendee(['event_occurrence_id' => 8])));
        $this->api->shouldReceive('get')->with(GetCheckInListsAction::class, ['event_id' => 12], Mockery::any())->andReturn($this->ok([
            ['id' => 1, 'name' => 'Default', 'short_id' => 'cil_default', 'is_system_default' => true, 'event_occurrence_id' => null, 'products' => [], 'is_active' => true, 'is_expired' => false],
            ['id' => 2, 'name' => 'Expired', 'short_id' => 'cil_old', 'is_system_default' => false, 'event_occurrence_id' => 8, 'products' => [], 'is_active' => true, 'is_expired' => true],
            ['id' => 3, 'name' => 'Friday door', 'short_id' => 'cil_friday', 'is_system_default' => false, 'event_occurrence_id' => 8, 'products' => [['id' => 5]], 'is_active' => true, 'is_expired' => false],
            ['id' => 4, 'name' => 'Other ticket', 'short_id' => 'cil_other', 'is_system_default' => false, 'event_occurrence_id' => 8, 'products' => [['id' => 6]], 'is_active' => true, 'is_expired' => false],
        ]));
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateAttendeeCheckInPublicAction::class, ['check_in_list_short_id' => 'cil_friday'], [
                'attendees' => [['public_id' => 'A-JANE1', 'action' => 'check-in']],
            ])
            ->andReturn($this->ok([['attendee_id' => 40, 'checked_in_at' => '2026-12-12 01:30:00']]));

        $result = $this->structured((new CheckInAttendeeTool($this->api))->handle(new Request(['event_id' => 12, 'attendee_id' => 40])));

        $this->assertSame(['id' => 3, 'name' => 'Friday door'], $result['check_in_list']);
        $this->assertSame('2026-12-11T20:30:00-05:00', $result['checked_in_at']);
        $this->assertFalse($result['already_checked_in']);
    }

    public function test_check_in_reports_an_already_checked_in_attendee_and_refusals(): void
    {
        $this->api->shouldReceive('get')->with(GetAttendeeAction::class, ['event_id' => 12, 'attendee_id' => 40])->andReturn($this->ok($this->attendee()));
        $this->api->shouldReceive('get')->with(GetCheckInListsAction::class, ['event_id' => 12], Mockery::any())->andReturn($this->ok([
            ['id' => 1, 'name' => 'Default', 'short_id' => 'cil_default', 'is_system_default' => true, 'event_occurrence_id' => null, 'products' => []],
        ]));
        $this->api->shouldReceive('send')->once()->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_OK, body: [
            'data' => [['attendee_id' => 40, 'checked_in_at' => '2026-12-12 01:30:00']],
            'errors' => ['A-JANE1' => 'Attendee Jane Doe is already checked in'],
        ]));
        $this->api->shouldReceive('send')->once()->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_OK, body: [
            'data' => [],
            'errors' => ['A-JANE1' => 'Unable to check in as attendee Jane Doe\'s order is awaiting payment'],
        ]));

        $tool = new CheckInAttendeeTool($this->api);

        $this->assertTrue($this->structured($tool->handle(new Request(['event_id' => 12, 'attendee_id' => 40])))['already_checked_in']);
        $this->assertStringContainsString('awaiting payment', $this->errorText($tool->handle(new Request(['event_id' => 12, 'attendee_id' => 40]))));
        $this->assertSame('That check-in list does not belong to this event.', $this->errorText($tool->handle(new Request(['event_id' => 12, 'attendee_id' => 40, 'check_in_list_id' => 99]))));
    }

    public function test_a_complimentary_attendee_is_free_never_overbooks_and_gets_their_ticket(): void
    {
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateAttendeeAction::class, ['event_id' => 12], [
                'product_id' => 5,
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'amount_paid' => 0,
                'send_confirmation_email' => true,
                'locale' => 'en',
                'override_capacity' => false,
            ])
            ->andReturn($this->ok($this->attendee(), ResponseCodes::HTTP_CREATED));

        $result = $this->structured((new AddAttendeeTool($this->api))->handle(new Request([
            'event_id' => 12, 'ticket_id' => 5, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
        ])));

        $this->assertSame(40, $result['attendee']['id']);
    }

    public function test_a_ticket_can_be_resent_once_per_cooldown(): void
    {
        $this->api->shouldReceive('get')->with(GetAttendeeAction::class, ['event_id' => 12, 'attendee_id' => 40])->andReturn($this->ok($this->attendee()));
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', ResendAttendeeTicketAction::class, ['event_id' => 12, 'attendee_public_id' => 40])
            ->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_NO_CONTENT, body: []));

        $tool = new ResendTicketTool($this->api);

        $this->assertTrue($this->structured($tool->handle(new Request(['event_id' => 12, 'attendee_id' => 40])))['ticket_sent']);
        $this->assertStringContainsString('less than 10 minutes ago', $this->errorText($tool->handle(new Request(['event_id' => 12, 'attendee_id' => 40]))));
    }

    public function test_a_failed_resend_does_not_start_the_cooldown(): void
    {
        $this->api->shouldReceive('get')->with(GetAttendeeAction::class, ['event_id' => 12, 'attendee_id' => 41])->andReturn($this->ok($this->attendee(['id' => 41])));
        $this->api->shouldReceive('send')->twice()->andReturn(
            $this->failed(ResponseCodes::HTTP_CONFLICT, ['message' => 'You cannot resend the ticket of an inactive attendee']),
            new ApiRouteResponseDTO(status: ResponseCodes::HTTP_NO_CONTENT, body: []),
        );

        $tool = new ResendTicketTool($this->api);

        $this->assertSame('You cannot resend the ticket of an inactive attendee', $this->errorText($tool->handle(new Request(['event_id' => 12, 'attendee_id' => 41]))));
        $this->structured($tool->handle(new Request(['event_id' => 12, 'attendee_id' => 41])));
    }

    public function test_only_a_completed_order_confirmation_is_resent(): void
    {
        $this->api->shouldReceive('get')->with(GetOrderAction::class, ['event_id' => 12, 'order_id' => 7])->andReturn($this->ok($this->order()));
        $this->api->shouldReceive('get')->with(GetOrderAction::class, ['event_id' => 12, 'order_id' => 8])->andReturn($this->ok($this->order(['id' => 8, 'status' => 'AWAITING_OFFLINE_PAYMENT'])));
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', ResendOrderConfirmationAction::class, ['event_id' => 12, 'order_id' => 7])
            ->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_NO_CONTENT, body: []));

        $tool = new ResendOrderConfirmationTool($this->api);

        $this->assertTrue($this->structured($tool->handle(new Request(['event_id' => 12, 'order_id' => 7])))['confirmation_sent']);
        $this->assertStringContainsString('less than 10 minutes ago', $this->errorText($tool->handle(new Request(['event_id' => 12, 'order_id' => 7]))));
        $this->assertSame('Only completed orders have a confirmation to resend.', $this->errorText($tool->handle(new Request(['event_id' => 12, 'order_id' => 8]))));
    }

    public function test_one_inbox_gets_at_most_the_email_budget_however_many_attendees_share_it(): void
    {
        $attendeeIds = range(50, 50 + ResendTicketTool::EMAILS_PER_ADDRESS_PER_COOLDOWN);
        foreach ($attendeeIds as $attendeeId) {
            $this->api->shouldReceive('get')->with(GetAttendeeAction::class, ['event_id' => 12, 'attendee_id' => $attendeeId])
                ->andReturn($this->ok($this->attendee(['id' => $attendeeId, 'email' => 'Shared@Example.com '])));
        }
        $this->api->shouldReceive('send')->times(ResendTicketTool::EMAILS_PER_ADDRESS_PER_COOLDOWN)
            ->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_NO_CONTENT, body: []));
        $tool = new ResendTicketTool($this->api);

        foreach (array_slice($attendeeIds, 0, -1) as $attendeeId) {
            $this->structured($tool->handle(new Request(['event_id' => 12, 'attendee_id' => $attendeeId])));
        }

        $this->assertStringContainsString('already been sent 5 emails', $this->errorText($tool->handle(new Request(['event_id' => 12, 'attendee_id' => end($attendeeIds)]))));
        $this->assertStringContainsString('already been sent 5 emails', $this->errorText((new AddAttendeeTool($this->api))->handle(new Request([
            'event_id' => 12, 'ticket_id' => 5, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'shared@example.com',
        ]))));
    }

    public function test_an_attendee_added_without_a_ticket_email_spends_no_email_budget(): void
    {
        RateLimiter::increment('mcp-mail:'.hash('sha256', 'jane@example.com'), amount: AddAttendeeTool::EMAILS_PER_ADDRESS_PER_COOLDOWN);
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateAttendeeAction::class, ['event_id' => 12], Mockery::on(static fn (array $body): bool => $body['send_confirmation_email'] === false))
            ->andReturn($this->ok($this->attendee(), ResponseCodes::HTTP_CREATED));

        $this->structured((new AddAttendeeTool($this->api))->handle(new Request([
            'event_id' => 12, 'ticket_id' => 5, 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'send_ticket' => false,
        ])));
    }

    private function attendee(array $overrides = []): array
    {
        return [
            'id' => 40,
            'public_id' => 'A-JANE1',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'status' => 'ACTIVE',
            'product_id' => 5,
            'product_price_id' => 9,
            'event_occurrence_id' => null,
            'order_id' => 7,
            ...$overrides,
        ];
    }

    private function order(array $overrides = []): array
    {
        return [
            'id' => 7,
            'public_id' => 'O-ABC123',
            'status' => 'COMPLETED',
            'payment_status' => 'PAYMENT_RECEIVED',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'created_at' => '2026-10-01 12:00:00',
            'currency' => 'USD',
            'total_gross' => 40.0,
            'event_id' => 12,
            'order_items' => [['product_id' => 5, 'item_name' => 'VIP', 'quantity' => 2, 'price' => 20.0, 'event_occurrence_id' => null]],
            ...$overrides,
        ];
    }
}
