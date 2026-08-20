<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Request\Order;

use HiEvents\Http\Request\Order\CreateOrderRequest;
use Illuminate\Routing\Route;
use Tests\TestCase;

class CreateOrderRequestTest extends TestCase
{
    public function test_rules_with_bound_route_returns_no_rules(): void
    {
        $request = new CreateOrderRequest;
        $request->setRouteResolver(static fn () => new Route(['POST'], '/events/{event_id}/order', []));

        $this->assertSame([], $request->rules());
    }

    public function test_rules_without_bound_route_returns_static_documentation_shape(): void
    {
        $rules = (new CreateOrderRequest)->rules();

        $this->assertSame(['required', 'array'], $rules['products']);
        $this->assertSame(['required', 'integer'], $rules['products.*.product_id']);
        $this->assertSame(['integer', 'nullable'], $rules['products.*.event_occurrence_id']);
        $this->assertSame(['required', 'integer', 'min:0'], $rules['products.*.quantities.*.quantity']);
        $this->assertArrayHasKey('promo_code', $rules);
        $this->assertArrayHasKey('affiliate_code', $rules);
    }
}
