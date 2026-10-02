<?php

namespace Tests\Feature\Http;

use HiEvents\Helper\CorsAllowedOrigins;
use Tests\TestCase;

class CorsCredentialsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cors.allowed_origins' => CorsAllowedOrigins::resolve(CorsAllowedOrigins::WILDCARD, 'https://tickets.example.com')]);
    }

    public function test_another_site_cannot_make_credentialed_requests_even_with_a_wildcard_configured(): void
    {
        $response = $this->call('OPTIONS', '/users/me', server: [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertNotSame('https://evil.example', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_the_frontend_can_make_credentialed_requests(): void
    {
        $response = $this->call('OPTIONS', '/users/me', server: [
            'HTTP_ORIGIN' => 'https://tickets.example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertSame('https://tickets.example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }
}
