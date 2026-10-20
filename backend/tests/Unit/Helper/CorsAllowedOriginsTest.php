<?php

namespace Tests\Unit\Helper;

use HiEvents\Helper\CorsAllowedOrigins;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CorsAllowedOriginsTest extends TestCase
{
    public static function configurations(): array
    {
        return [
            'unset falls back to the frontend origin' => [null, 'https://tickets.example.com/', ['https://tickets.example.com', 'https://www.tickets.example.com']],
            'blank falls back to the frontend origin' => ['', 'https://tickets.example.com', ['https://tickets.example.com', 'https://www.tickets.example.com']],
            'a wildcard is narrowed to the frontend origin' => ['*', 'https://tickets.example.com', ['https://tickets.example.com', 'https://www.tickets.example.com']],
            'a wildcard mixed with origins is still narrowed' => ['https://a.example.com, *', 'https://tickets.example.com', ['https://tickets.example.com', 'https://www.tickets.example.com']],
            'the frontend path is not part of the origin' => [null, 'https://example.com/tickets', ['https://example.com', 'https://www.example.com']],
            'a www frontend also allows the bare domain' => [null, 'https://www.example.com', ['https://www.example.com', 'https://example.com']],
            'an http frontend url also allows https, for installs behind a tls proxy' => [null, 'http://example.com', ['https://example.com', 'https://www.example.com', 'http://example.com', 'http://www.example.com']],
            'an https frontend url never allows http' => [null, 'https://example.com:8443', ['https://example.com:8443', 'https://www.example.com:8443']],
            'localhost and ip hosts get no www counterpart' => ['*', 'http://localhost:8123', ['https://localhost:8123', 'http://localhost:8123']],
            'a frontend url without a scheme allows both schemes' => [null, 'tickets.example.com', ['https://tickets.example.com', 'https://www.tickets.example.com', 'http://tickets.example.com', 'http://www.tickets.example.com']],
            'explicit origins are kept as listed' => ['https://a.example.com, https://b.example.com', 'https://tickets.example.com', ['https://a.example.com', 'https://b.example.com']],
            'origin patterns are kept' => ['https://*.example.com', 'https://tickets.example.com', ['https://*.example.com']],
            'an install without a frontend url keeps working as it did before the upgrade' => [null, '', ['*']],
            'a wildcard without a frontend url keeps working as it did before the upgrade' => ['*', null, ['*']],
        ];
    }

    #[DataProvider('configurations')]
    public function test_it_resolves_the_allowed_origins(?string $configured, ?string $frontendUrl, array $expected): void
    {
        $this->assertSame($expected, CorsAllowedOrigins::resolve($configured, $frontendUrl));
    }
}
