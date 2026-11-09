<?php

namespace Tests\Unit\Mcp\Tools\Support;

use HiEvents\Mcp\Tools\Support\WidgetEmbedCode;
use Tests\TestCase;

class WidgetEmbedCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.frontend_url' => 'https://app.example.test/']);
    }

    public function test_a_ticket_widget_has_no_type_attribute(): void
    {
        $this->assertSame(
            "<div class=\"hievents-widget\"\n  data-hievents-id=\"12\"\n  data-hievents-widget=\"wgt_abc\"\n></div>\n<script async src=\"https://app.example.test/widget.js\"></script>",
            WidgetEmbedCode::forWidget(['type' => 'TICKETS', 'event_id' => 12, 'organizer_id' => 3, 'short_id' => 'wgt_abc', 'settings' => ['primary_color' => '#000000']]),
        );
    }

    public function test_a_button_carries_its_host_keys_escaped(): void
    {
        $code = WidgetEmbedCode::forWidget([
            'type' => 'BUTTON',
            'event_id' => 12,
            'organizer_id' => 3,
            'short_id' => 'wgt_btn',
            'settings' => ['button_text' => 'Tickets & "more"', 'secondary_color' => '#E11D48', 'radius' => 8, 'primary_color' => '#000000'],
        ]);

        $this->assertStringContainsString('data-hievents-widget-type="button"', $code);
        $this->assertStringContainsString('data-hievents-button-text="Tickets &amp; &quot;more&quot;"', $code);
        $this->assertStringContainsString('data-hievents-secondary-color="#E11D48"', $code);
        $this->assertStringContainsString('data-hievents-radius="8"', $code);
        $this->assertStringNotContainsString('primary-color', str_replace('secondary-color', '', $code));
    }

    public function test_an_events_widget_targets_the_organizer_and_keeps_autoresize_off(): void
    {
        $code = WidgetEmbedCode::forWidget([
            'type' => 'EVENTS',
            'event_id' => null,
            'organizer_id' => 3,
            'short_id' => 'wgt_list',
            'settings' => ['autoresize' => false, 'layout' => 'grid'],
        ]);

        $this->assertStringContainsString('data-hievents-organizer-id="3"', $code);
        $this->assertStringContainsString('data-hievents-widget-type="events"', $code);
        $this->assertStringContainsString('data-hievents-autoresize="false"', $code);
        $this->assertStringNotContainsString('layout', $code);
    }
}
