<?php

namespace Tests\Unit\Helper;

use HiEvents\Helper\AuthCookieSameSite;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthCookieSameSiteTest extends TestCase
{
    public static function requests(): array
    {
        return [
            'a same-origin request gets a lax cookie' => [['Sec-Fetch-Site' => 'same-origin'], 'Lax'],
            'a same-site request gets a lax cookie' => [['Sec-Fetch-Site' => 'same-site', 'Origin' => 'https://app.example.com'], 'Lax'],
            'a cross-site frontend keeps the cookie it could store before the upgrade' => [['Sec-Fetch-Site' => 'cross-site'], 'None'],
            'the fetch site header wins over the origin' => [['Sec-Fetch-Site' => 'same-site', 'Origin' => 'https://tickets.other.com'], 'Lax'],
            'a non-browser client gets a lax cookie' => [[], 'Lax'],
            'a browser without fetch metadata on the api host gets a lax cookie' => [['Origin' => 'https://api.example.com'], 'Lax'],
            'a browser without fetch metadata on another host keeps a storable cookie' => [['Origin' => 'https://tickets.other.com'], 'None'],
            'an unparseable origin gets a lax cookie' => [['Origin' => 'null'], 'Lax'],
        ];
    }

    #[DataProvider('requests')]
    public function test_it_picks_the_strictest_same_site_the_browser_will_store(array $headers, string $expected): void
    {
        $request = Request::create('https://api.example.com/auth/login', 'POST');
        $request->headers->add($headers);

        $this->assertSame($expected, AuthCookieSameSite::forRequest($request));
    }
}
