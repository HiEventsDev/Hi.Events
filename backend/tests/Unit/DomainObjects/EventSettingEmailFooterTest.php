<?php

namespace Tests\Unit\DomainObjects;

use HiEvents\DomainObjects\EventSettingDomainObject;
use Illuminate\Mail\Markdown;
use Tests\TestCase;

class EventSettingEmailFooterTest extends TestCase
{
    public function test_a_footer_with_blank_lines_is_never_parsed_as_markdown(): void
    {
        $settings = (new EventSettingDomainObject)->setEmailFooterMessage("<p>See you there</p>\n\n![](https://tracker.example/pixel.png)\n\n[Refund](https\\://phish.example)");

        $footer = $settings->getGetEmailFooterHtml();
        $sent = (string) Markdown::parse("Hello\n\n$footer\n\nGoodbye");

        $this->assertStringNotContainsString("\n", $footer);
        $this->assertStringContainsString('<p>See you there</p> ![](https://tracker.example/pixel.png)', $sent);
        $this->assertStringNotContainsString('<img', $sent);
        $this->assertStringNotContainsString('href="https://phish.example', $sent);
    }

    public function test_an_event_without_a_footer_adds_nothing(): void
    {
        $this->assertSame('', (new EventSettingDomainObject)->getGetEmailFooterHtml());
    }
}
