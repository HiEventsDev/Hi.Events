<?php

namespace Tests\Unit\Http\Middleware;

use HiEvents\Http\Middleware\RejectPublishedSecrets;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class RejectPublishedSecretsTest extends TestCase
{
    public function test_requests_are_refused_while_the_example_jwt_secret_is_configured(): void
    {
        config(['jwt.secret' => '2hoccgHb9r1fqW1lU16C6khSHVa7O0eai6FxkWK95UtQ0LqNDTO5mq1RzDwcq18I']);

        $response = (new RejectPublishedSecrets)->handle(Request::create('/public/events/1'), fn () => new Response('ok'));

        $this->assertSame(503, $response->getStatusCode());
    }

    public function test_requests_are_served_with_an_installation_specific_secret(): void
    {
        config(['jwt.secret' => 'an-installation-specific-secret']);

        $response = (new RejectPublishedSecrets)->handle(Request::create('/public/events/1'), fn () => new Response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }
}
