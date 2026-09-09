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
}
