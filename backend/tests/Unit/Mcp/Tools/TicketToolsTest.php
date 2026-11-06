<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\ProductCategories\GetProductCategoriesAction;
use HiEvents\Http\Actions\Products\CreateProductAction;
use HiEvents\Http\Actions\Products\EditProductAction;
use HiEvents\Http\Actions\Products\GetProductAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Tools\CreateTicketTool;
use HiEvents\Mcp\Tools\UpdateTicketTool;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Mockery;

class TicketToolsTest extends McpToolTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
    }

    public function test_create_ticket_uses_the_default_category_and_a_single_price(): void
    {
        $this->api->shouldReceive('get')->with(GetProductCategoriesAction::class, ['event_id' => 12])->andReturn($this->ok([['id' => 4], ['id' => 5]]));
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateProductAction::class, ['event_id' => 12], [
                'title' => 'GA',
                'type' => 'PAID',
                'product_type' => 'TICKET',
                'product_category_id' => 4,
                'prices' => [['price' => 15, 'initial_quantity_available' => 120]],
            ])
            ->andReturn($this->ok($this->product(), ResponseCodes::HTTP_CREATED));

        $result = $this->structured((new CreateTicketTool($this->api))->handle(new Request([
            'event_id' => 12, 'title' => 'GA', 'type' => 'PAID', 'price' => 15, 'quantity_available' => 120,
        ])));

        $this->assertSame(5, $result['ticket']['id']);
    }

    public function test_create_ticket_maps_tiers_to_prices(): void
    {
        $this->api->shouldReceive('get')->with(GetProductCategoriesAction::class, ['event_id' => 12])->andReturn($this->ok([['id' => 4]]));
        $this->api->shouldReceive('send')
            ->once()
            ->withArgs(fn (string $method, string $action, array $parameters, array $body) => $body['prices'] === [
                ['label' => 'Early bird', 'price' => 10, 'initial_quantity_available' => 30],
                ['label' => 'Standard', 'price' => 18],
            ] && $body['type'] === 'TIERED')
            ->andReturn($this->ok($this->product(['type' => 'TIERED'])));

        $this->structured((new CreateTicketTool($this->api))->handle(new Request([
            'event_id' => 12,
            'title' => 'Conference pass',
            'type' => 'TIERED',
            'price' => 99,
            'tiers' => [['label' => 'Early bird', 'price' => 10, 'quantity_available' => 30], ['label' => 'Standard', 'price' => 18]],
        ])));
    }

    public function test_create_ticket_passes_an_explicit_null_price_through_for_the_api_to_reject(): void
    {
        $this->api->shouldReceive('get')->with(GetProductCategoriesAction::class, ['event_id' => 12])->andReturn($this->ok([['id' => 4]]));
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateProductAction::class, ['event_id' => 12], Mockery::on(static fn (array $body): bool => $body['prices'][0]['price'] === null && $body['product_type'] === 'TICKET'))
            ->andReturn($this->failed(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY, ['errors' => ['prices.0.price' => ['The price is required.']]]));

        $this->assertSame('prices.0.price: The price is required.', $this->errorText((new CreateTicketTool($this->api))->handle(new Request([
            'event_id' => 12, 'title' => 'GA', 'type' => 'PAID', 'price' => null,
        ]))));
    }

    public function test_create_ticket_rejects_unsupported_types_before_calling_the_api(): void
    {
        $this->api->shouldNotReceive('send');

        $this->expectException(ValidationException::class);

        (new CreateTicketTool($this->api))->handle(new Request(['event_id' => 12, 'title' => 'X', 'type' => 'REGISTRATION']));
    }

    public function test_update_ticket_keeps_every_existing_setting_and_applies_the_change(): void
    {
        $this->api->shouldReceive('get')->with(GetProductAction::class, ['event_id' => 12, 'ticket_id' => 5])->andReturn($this->ok($this->product([
            'hide_when_sold_out' => true,
            'taxes_and_fees' => [['id' => 7]],
            'sale_end_date' => '2026-12-12 00:00:00',
        ])));
        $this->api->shouldReceive('send')
            ->once()
            ->withArgs(function (string $method, string $action, array $parameters, array $body) {
                return $method === 'PUT'
                    && $action === EditProductAction::class
                    && $parameters === ['event_id' => 12, 'ticket_id' => 5]
                    && $body['title'] === 'General admission'
                    && $body['hide_when_sold_out'] === true
                    && $body['tax_and_fee_ids'] === [7]
                    && $body['sale_end_date'] === '2026-12-11 19:00'
                    && $body['prices'][0]['id'] === 9
                    && $body['prices'][0]['price'] === 17.5
                    && $body['prices'][0]['initial_quantity_available'] === null;
            })
            ->andReturn($this->ok($this->product()));

        $this->structured((new UpdateTicketTool($this->api))->handle(new Request([
            'event_id' => 12, 'ticket_id' => 5, 'title' => 'General admission', 'price' => 17.5, 'quantity_available' => null,
        ])));
    }

    public function test_update_ticket_merges_tier_changes_onto_existing_tiers(): void
    {
        $existingTier = ['id' => 9, 'label' => 'Early', 'price' => 15, 'initial_quantity_available' => 100, 'is_hidden' => true, 'quantity_applies_to' => 'EVENT'];
        $this->api->shouldReceive('get')->with(GetProductAction::class, ['event_id' => 12, 'ticket_id' => 5])->andReturn($this->ok($this->product(['type' => 'TIERED', 'prices' => [$existingTier]])));
        $this->api->shouldReceive('send')
            ->once()
            ->withArgs(fn (string $method, string $action, array $parameters, array $body) => $body['prices'] === [
                [
                    'id' => 9,
                    'label' => 'Early bird',
                    'price' => 11,
                    'initial_quantity_available' => 100,
                    'sale_start_date' => null,
                    'sale_end_date' => null,
                    'is_hidden' => true,
                    'quantity_applies_to' => 'EVENT',
                ],
                ['label' => 'Late', 'price' => 22],
            ])
            ->andReturn($this->ok($this->product(['type' => 'TIERED'])));

        $this->structured((new UpdateTicketTool($this->api))->handle(new Request([
            'event_id' => 12, 'ticket_id' => 5, 'tiers' => [['id' => 9, 'label' => 'Early bird', 'price' => 11], ['label' => 'Late', 'price' => 22]],
        ])));
    }

    public function test_update_ticket_can_make_an_existing_tier_unlimited(): void
    {
        $existingTier = ['id' => 9, 'label' => 'Early', 'price' => 15, 'initial_quantity_available' => 100, 'is_hidden' => false];
        $this->api->shouldReceive('get')->with(GetProductAction::class, ['event_id' => 12, 'ticket_id' => 5])->andReturn($this->ok($this->product(['type' => 'TIERED', 'prices' => [$existingTier]])));
        $this->api->shouldReceive('send')
            ->once()
            ->withArgs(fn (string $method, string $action, array $parameters, array $body) => $body['prices'][0]['initial_quantity_available'] === null
                && $body['prices'][0]['price'] === 15)
            ->andReturn($this->ok($this->product(['type' => 'TIERED'])));

        $this->structured((new UpdateTicketTool($this->api))->handle(new Request([
            'event_id' => 12, 'ticket_id' => 5, 'tiers' => [['id' => 9, 'quantity_available' => null]],
        ])));
    }

    public function test_update_ticket_reports_a_ticket_from_another_event_as_not_found(): void
    {
        $this->api->shouldReceive('get')->with(GetProductAction::class, ['event_id' => 12, 'ticket_id' => 999])->andReturn($this->failed(ResponseCodes::HTTP_NOT_FOUND, ['message' => 'Resource not found']));
        $this->api->shouldNotReceive('send');

        $this->assertSame('Not found in this account.', $this->errorText((new UpdateTicketTool($this->api))->handle(new Request([
            'event_id' => 12, 'ticket_id' => 999, 'title' => 'X',
        ]))));
    }

    private function product(array $overrides = []): array
    {
        return [
            'id' => 5,
            'title' => 'GA',
            'type' => 'PAID',
            'product_type' => 'TICKET',
            'product_category_id' => 4,
            'is_hidden' => false,
            'prices' => [['id' => 9, 'label' => null, 'price' => 15, 'initial_quantity_available' => 120, 'is_hidden' => false]],
            ...$overrides,
        ];
    }
}
