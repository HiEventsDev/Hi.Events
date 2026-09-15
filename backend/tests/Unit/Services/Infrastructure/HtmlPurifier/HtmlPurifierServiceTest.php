<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Infrastructure\HtmlPurifier;

use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HtmlPurifierServiceTest extends TestCase
{
    private HtmlPurifierService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(HtmlPurifierService::class);
    }

    public function test_links_are_not_followable(): void
    {
        $purified = $this->service->purify('<p><a href="https://spam.example/money">cheap backlinks</a></p>');

        $this->assertStringContainsString('rel="nofollow', $purified);
        $this->assertStringContainsString('target="_blank"', $purified);
    }

    public function test_a_declared_rel_cannot_opt_out_of_nofollow(): void
    {
        $purified = $this->service->purify('<a href="https://spam.example" rel="dofollow">x</a>');

        $this->assertStringContainsString('nofollow', $purified);
        $this->assertStringNotContainsString('dofollow', $purified);
    }

    public function test_scripts_are_still_removed(): void
    {
        $this->assertStringNotContainsString('alert', (string) $this->service->purify('<script>alert(1)</script>Hi'));
    }

    #[DataProvider('liquidTokenInUriProvider')]
    public function test_liquid_tokens_survive_purification_inside_uris(string $html, string $expectedHref): void
    {
        $purified = (string) $this->service->purify($html);

        $this->assertStringContainsString($expectedHref, $purified);
        $this->assertStringNotContainsString('%7B%7B', $purified);
    }

    public static function liquidTokenInUriProvider(): array
    {
        return [
            'query string' => [
                '<a href="https://example.com/orders?ref={{ order.number }}">View</a>',
                'href="https://example.com/orders?ref={{ order.number }}"',
            ],
            'path segment' => [
                '<a href="https://example.com/{{ event.slug }}/tickets">View</a>',
                'href="https://example.com/{{ event.slug }}/tickets"',
            ],
            'fragment' => [
                '<a href="https://example.com/orders#{{ order.id }}">View</a>',
                'href="https://example.com/orders#{{ order.id }}"',
            ],
            'entire href' => [
                '<a href="{{ order.url }}">View</a>',
                'href="{{ order.url }}"',
            ],
            'token with filter' => [
                '<a href="https://example.com/{{ event.slug | downcase }}">View</a>',
                'href="https://example.com/{{ event.slug | downcase }}"',
            ],
            'multiple tokens' => [
                '<a href="https://example.com/o?ref={{ order.number }}&t={{ order.id }}">View</a>',
                'href="https://example.com/o?ref={{ order.number }}&amp;t={{ order.id }}"',
            ],
            'token without surrounding spaces' => [
                '<a href="https://example.com/o?ref={{order.number}}">View</a>',
                'href="https://example.com/o?ref={{order.number}}"',
            ],
        ];
    }

    public function test_liquid_tokens_in_text_nodes_are_untouched(): void
    {
        $html = '<p>Hi {{ order.first_name }}</p><p>{% if order.is_payment_required %}Due{% endif %}</p>';

        $this->assertSame($html, $this->service->purify($html));
    }

    public function test_liquid_href_still_gets_nofollow_and_target_blank(): void
    {
        $purified = (string) $this->service->purify('<a href="https://spam.example/?r={{ order.number }}">x</a>');

        $this->assertStringContainsString('{{ order.number }}', $purified);
        $this->assertStringContainsString('rel="nofollow', $purified);
        $this->assertStringContainsString('target="_blank"', $purified);
    }

    #[DataProvider('hostileLiquidTokenProvider')]
    public function test_liquid_tokens_cannot_smuggle_markup_past_the_purifier(string $html, string $mustNotContain): void
    {
        $purified = (string) $this->service->purify($html);

        $this->assertStringNotContainsString($mustNotContain, $purified);
    }

    public static function hostileLiquidTokenProvider(): array
    {
        return [
            'script tag in a string literal' => [
                '<p>{{ \'<script>alert(1)</script>\' }}</p>',
                '<script',
            ],
            'event handler in a string literal' => [
                '<p>{{ "<img src=x onerror=alert(1)>" }}</p>',
                'onerror',
            ],
            'attribute breakout' => [
                '<a href="{{ " onmouseover="alert(1) }}">hi</a>',
                'onmouseover',
            ],
            'quote smuggled inside a token' => [
                '<a href="https://example.com/{{ order.a" onmouseover="alert(1) }}">x</a>',
                'onmouseover',
            ],
            'javascript scheme inside a token' => [
                '<a href="{{ javascript:alert(1) }}">x</a>',
                'javascript:',
            ],
        ];
    }

    #[DataProvider('unrelatedMarkupProvider')]
    public function test_purification_of_non_liquid_markup_is_unchanged(string $html, string $expected): void
    {
        $this->assertSame($expected, $this->service->purify($html));
    }

    public static function unrelatedMarkupProvider(): array
    {
        return [
            'relative link' => ['<a href="/manage/orders">L</a>', '<a href="/manage/orders">L</a>'],
            'mailto' => ['<a href="mailto:a@b.com">M</a>', '<a href="mailto:a@b.com">M</a>'],
            'image' => ['<img src="https://example.com/i.png" alt="x">', '<img src="https://example.com/i.png" alt="x" />'],
            'javascript scheme is stripped' => ['<a href="javascript:alert(1)">x</a>', '<a>x</a>'],
            'data uri is stripped' => ['<img src="data:text/html;base64,PHNjcmlwdD4=" alt="x">', ''],
            'event handler is stripped' => ['<img src="x" onerror="alert(1)" alt="x">', '<img src="x" alt="x" />'],
        ];
    }

    public function test_percent_encoded_braces_typed_by_hand_also_decode(): void
    {
        $this->assertSame('<p>{{foo}}</p>', $this->service->purify('<p>%7B%7Bfoo%7D%7D</p>'));
    }

    public function test_decoding_can_only_emit_characters_that_are_inert_in_markup(): void
    {
        $purified = (string) $this->service->purify(
            '<a href="https://example.com/?a=%7B%7B%22onerror%3D%3Cscript%3E%7D%7D">x</a>'
        );

        $this->assertStringNotContainsString('<script', $purified);
        $this->assertStringNotContainsString('onerror=', $purified);
    }

    public function test_null_is_preserved(): void
    {
        $this->assertNull($this->service->purify(null));
    }
}
