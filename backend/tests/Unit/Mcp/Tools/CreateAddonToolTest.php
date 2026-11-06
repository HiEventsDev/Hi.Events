<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\ProductCategories\GetProductCategoriesAction;
use HiEvents\Http\Actions\Products\CreateProductAction;
use HiEvents\Http\Actions\Products\DeleteProductAction;
use HiEvents\Http\Actions\Products\EditProductAction;
use HiEvents\Http\Actions\Products\GetProductAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Tools\CreateAddonTool;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Mockery;

class CreateAddonToolTest extends McpToolTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('get')->with(GetProductCategoriesAction::class, ['event_id' => 12])->andReturn($this->ok([['id' => 4]]));
    }

    public function test_an_addon_only_product_is_created_and_appended_to_each_tickets_addons(): void
    {
        $this->parent(5, ['addon_product_ids' => [30]]);
        $this->parent(6);
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateProductAction::class, ['event_id' => 12], [
                'title' => 'Parking',
                'type' => 'PAID',
                'product_type' => 'GENERAL',
                'product_category_id' => 4,
                'prices' => [['price' => 10, 'initial_quantity_available' => 50]],
                'is_addon_only' => true,
            ])
            ->andReturn($this->ok($this->product(40, ['title' => 'Parking', 'product_type' => 'GENERAL', 'is_addon_only' => true]), ResponseCodes::HTTP_CREATED));
        $this->expectLink(5, [30, 40]);
        $this->expectLink(6, [40]);
        $this->api->shouldNotReceive('send')->with('DELETE', Mockery::any(), Mockery::any());

        $result = $this->structured((new CreateAddonTool($this->api))->handle(new Request([
            'event_id' => 12, 'ticket_ids' => [5, 6, 5], 'title' => 'Parking', 'type' => 'PAID', 'price' => 10, 'quantity_available' => 50,
        ])));

        $this->assertSame(40, $result['addon']['id']);
        $this->assertTrue($result['addon']['is_addon_only']);
        $this->assertSame([5, 6], $result['offered_with_ticket_ids']);
    }

    public function test_an_addon_sold_separately_is_not_addon_only(): void
    {
        $this->parent(5);
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateProductAction::class, ['event_id' => 12], Mockery::on(static fn (array $body): bool => $body['is_addon_only'] === false && $body['product_type'] === 'TICKET'))
            ->andReturn($this->ok($this->product(40), ResponseCodes::HTTP_CREATED));
        $this->expectLink(5, [40]);

        $result = $this->structured((new CreateAddonTool($this->api))->handle(new Request([
            'event_id' => 12, 'ticket_ids' => [5], 'title' => 'Workshop', 'type' => 'FREE', 'sold_separately' => true, 'product_type' => 'TICKET',
        ])));

        $this->assertSame([5], $result['offered_with_ticket_ids']);
    }

    public function test_a_failed_link_deletes_the_addon_which_unlinks_it_everywhere(): void
    {
        $this->parent(5, ['addon_product_ids' => [30]]);
        $this->parent(6);
        $this->api->shouldReceive('send')->once()->with('POST', CreateProductAction::class, Mockery::any(), Mockery::any())
            ->andReturn($this->ok($this->product(40), ResponseCodes::HTTP_CREATED));
        $this->expectLink(5, [30, 40]);
        $this->failLink(6);
        $this->api->shouldReceive('send')->once()->with('DELETE', DeleteProductAction::class, ['event_id' => 12, 'ticket_id' => 40])->andReturn($this->ok([]));

        $this->assertSame('title: Too long.', $this->errorText((new CreateAddonTool($this->api))->handle(new Request([
            'event_id' => 12, 'ticket_ids' => [5, 6], 'title' => 'Parking', 'type' => 'FREE',
        ]))));
    }

    public function test_a_failed_clean_up_is_reported_with_the_addon_id(): void
    {
        $this->parent(6);
        $this->api->shouldReceive('send')->once()->with('POST', CreateProductAction::class, Mockery::any(), Mockery::any())
            ->andReturn($this->ok($this->product(40), ResponseCodes::HTTP_CREATED));
        $this->failLink(6);
        $this->api->shouldReceive('send')->once()->with('DELETE', DeleteProductAction::class, ['event_id' => 12, 'ticket_id' => 40])
            ->andReturn($this->failed(ResponseCodes::HTTP_TOO_MANY_REQUESTS, []));

        $error = $this->errorText((new CreateAddonTool($this->api))->handle(new Request([
            'event_id' => 12, 'ticket_ids' => [6], 'title' => 'Parking', 'type' => 'FREE',
        ])));

        $this->assertStringStartsWith('title: Too long.', $error);
        $this->assertStringContainsString('The add-on (id 40) was created but could not be removed.', $error);
    }

    public function test_each_linked_ticket_counts_against_the_write_limit(): void
    {
        RateLimiter::increment('mcp-write::', amount: CreateAddonTool::MAX_WRITES_PER_MINUTE - 2);
        $this->api->shouldNotReceive('send');
        $this->api->shouldNotReceive('get')->with(GetProductAction::class, Mockery::any());

        $this->assertStringContainsString('Too many changes', $this->errorText((new CreateAddonTool($this->api))->handle(new Request([
            'event_id' => 12, 'ticket_ids' => [5, 6], 'title' => 'Parking', 'type' => 'FREE',
        ]))));
    }

    public function test_a_missing_or_addon_only_ticket_stops_before_anything_is_created(): void
    {
        $this->api->shouldReceive('get')->with(GetProductAction::class, ['event_id' => 12, 'ticket_id' => 7])->andReturn($this->failed(ResponseCodes::HTTP_NOT_FOUND, []));
        $this->parent(8, ['is_addon_only' => true]);
        $this->api->shouldNotReceive('send');

        $tool = new CreateAddonTool($this->api);

        $this->assertSame('Not found in this account.', $this->errorText($tool->handle(new Request(['event_id' => 12, 'ticket_ids' => [7], 'title' => 'Parking', 'type' => 'FREE']))));
        $this->assertStringContainsString('itself an add-on', $this->errorText($tool->handle(new Request(['event_id' => 12, 'ticket_ids' => [8], 'title' => 'Parking', 'type' => 'FREE']))));
    }

    public function test_an_addon_needs_at_least_one_ticket(): void
    {
        $this->api->shouldNotReceive('send');

        $this->expectException(ValidationException::class);

        (new CreateAddonTool($this->api))->handle(new Request(['event_id' => 12, 'ticket_ids' => [], 'title' => 'Parking', 'type' => 'FREE']));
    }

    private function parent(int $id, array $overrides = []): void
    {
        $this->api->shouldReceive('get')->with(GetProductAction::class, ['event_id' => 12, 'ticket_id' => $id])->andReturn($this->ok($this->product($id, $overrides)));
    }

    private function failLink(int $ticketId): void
    {
        $this->api->shouldReceive('send')
            ->once()
            ->with('PUT', EditProductAction::class, ['event_id' => 12, 'ticket_id' => $ticketId], Mockery::any())
            ->andReturn($this->failed(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY, ['errors' => ['title' => ['Too long.']]]));
    }

    private function expectLink(int $ticketId, array $addonIds): void
    {
        $this->api->shouldReceive('send')
            ->once()
            ->with('PUT', EditProductAction::class, ['event_id' => 12, 'ticket_id' => $ticketId], Mockery::on(
                static fn (array $body): bool => $body['addon_product_ids'] === $addonIds && $body['prices'][0]['id'] === 9,
            ))
            ->andReturn($this->ok($this->product($ticketId)));
    }

    private function product(int $id, array $overrides = []): array
    {
        return [
            'id' => $id,
            'title' => 'GA',
            'type' => 'PAID',
            'product_type' => 'TICKET',
            'product_category_id' => 4,
            'is_hidden' => false,
            'is_addon_only' => false,
            'addon_product_ids' => [],
            'prices' => [['id' => 9, 'label' => null, 'price' => 15, 'initial_quantity_available' => 120, 'is_hidden' => false]],
            ...$overrides,
        ];
    }
}
