<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Infrastructure\HtmlPurifier;

use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
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

    public function test_null_is_preserved(): void
    {
        $this->assertNull($this->service->purify(null));
    }

    public function test_purify_preserving_liquid_keeps_tokens_in_hrefs(): void
    {
        $html = '<p><a href="https://example.com/orders?ref={{ order.number }}">View order</a></p>';

        $result = $this->service->purifyPreservingLiquid($html);

        $this->assertStringContainsString('{{ order.number }}', $result);
        $this->assertStringNotContainsString('%7B%7B', $result);
        $this->assertStringNotContainsString('LIQUIDTOKEN', $result);
    }

    public function test_default_purify_still_encodes_braces_in_hrefs(): void
    {
        $html = '<p><a href="https://example.com/orders?ref={{ order.number }}">View order</a></p>';

        $result = $this->service->purify($html);

        $this->assertStringNotContainsString('{{ order.number }}', (string) $result);
        $this->assertStringContainsString('%7B%7B', (string) $result);
    }

    public function test_purify_preserving_liquid_keeps_tag_tokens(): void
    {
        $html = '<p>{% if order.is_paid %}<a href="https://example.com/{{ event.id }}">Paid</a>{% endif %}</p>';

        $result = $this->service->purifyPreservingLiquid($html);

        $this->assertStringContainsString('{% if order.is_paid %}', $result);
        $this->assertStringContainsString('{{ event.id }}', $result);
        $this->assertStringContainsString('{% endif %}', $result);
    }
}
