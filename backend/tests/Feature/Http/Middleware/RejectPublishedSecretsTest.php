<?php

namespace Tests\Feature\Http\Middleware;

use HiEvents\Helper\CorsAllowedOrigins;
use Tests\TestCase;

class RejectPublishedSecretsTest extends TestCase
{
    public function test_requests_are_refused_while_the_example_jwt_secret_is_configured(): void
    {
        config(['jwt.secret' => '2hoccgHb9r1fqW1lU16C6khSHVa7O0eai6FxkWK95UtQ0LqNDTO5mq1RzDwcq18I']);

        $this->getJson('/public/instance')->assertStatus(503);
    }

    public function test_the_refusal_reaches_the_frontend_instead_of_looking_like_a_cors_failure(): void
    {
        config([
            'jwt.secret' => '2hoccgHb9r1fqW1lU16C6khSHVa7O0eai6FxkWK95UtQ0LqNDTO5mq1RzDwcq18I',
            'cors.allowed_origins' => CorsAllowedOrigins::resolve(CorsAllowedOrigins::WILDCARD, 'https://tickets.example.com'),
        ]);

        $this->getJson('/public/instance', ['Origin' => 'https://tickets.example.com'])
            ->assertStatus(503)
            ->assertHeader('Access-Control-Allow-Origin', 'https://tickets.example.com');
    }

    public function test_requests_are_served_with_installation_specific_secrets(): void
    {
        $this->getJson('/public/instance')->assertOk();
    }
}
