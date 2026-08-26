<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Request\Order;

use HiEvents\Http\Request\Order\CompleteOrderRequest;
use HiEvents\Validators\CompleteOrderValidator;
use Illuminate\Routing\Route;
use Mockery;
use Tests\TestCase;

class CompleteOrderRequestTest extends TestCase
{
    public function test_rules_without_bound_route_returns_static_documentation_shape(): void
    {
        $request = new CompleteOrderRequest;

        $rules = $request->rules();

        $this->assertSame(['required', 'string', 'max:40'], $rules['order.first_name']);
        $this->assertSame(['required', 'email', 'same:order.email'], $rules['order.email_confirmation']);
        $this->assertArrayHasKey('order.questions', $rules);
        $this->assertArrayHasKey('order.address', $rules);
        $this->assertArrayHasKey('products', $rules);
    }

    public function test_rules_with_bound_route_delegates_to_complete_order_validator(): void
    {
        $validatorRules = ['order.first_name' => ['required']];

        $validator = Mockery::mock(CompleteOrderValidator::class);
        $validator->shouldReceive('rules')->once()->andReturn($validatorRules);
        $this->app->instance(CompleteOrderValidator::class, $validator);

        $request = new CompleteOrderRequest;
        $request->setRouteResolver(static fn () => new Route(['PUT'], '/events/{event_id}/order/{order_short_id}', []));

        $this->assertSame($validatorRules, $request->rules());
    }

    public function test_messages_delegates_to_complete_order_validator(): void
    {
        $messages = ['order.email' => 'A valid email is required'];

        $validator = Mockery::mock(CompleteOrderValidator::class);
        $validator->shouldReceive('messages')->once()->andReturn($messages);
        $this->app->instance(CompleteOrderValidator::class, $validator);

        $this->assertSame($messages, (new CompleteOrderRequest)->messages());
    }
}
