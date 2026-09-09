<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Infrastructure\HtmlPurifier;

use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use HTMLPurifier;
use Tests\TestCase;

class HtmlPurifierServiceTest extends TestCase
{
    private HtmlPurifierService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new HtmlPurifierService(new HTMLPurifier());
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
