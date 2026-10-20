<?php

namespace Tests\Unit\Services\Domain\Email;

use HiEvents\Services\Domain\Email\EmailContextHtmlEscaper;
use Tests\TestCase;

class EmailContextHtmlEscaperTest extends TestCase
{
    private EmailContextHtmlEscaper $escaper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->escaper = new EmailContextHtmlEscaper;
    }

    public function test_escapes_html_in_scalar_string_values(): void
    {
        $escaped = $this->escaper->escape([
            'event' => [
                'title' => '<script>alert(1)</script>',
            ],
            'organizer' => [
                'name' => 'Bob & <b>Sons</b>',
            ],
            'order' => [
                'first_name' => '<img src=x onerror=alert(1)>',
                'last_name' => '<a href="https://phish.example">Click</a>',
            ],
        ]);

        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $escaped['event']['title']);
        $this->assertSame('Bob &amp; &lt;b&gt;Sons&lt;/b&gt;', $escaped['organizer']['name']);
        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $escaped['order']['first_name']);
        $this->assertSame('&lt;a href=&quot;https://phish.example&quot;&gt;Click&lt;/a&gt;', $escaped['order']['last_name']);
    }

    public function test_escapes_nested_location_values(): void
    {
        $escaped = $this->escaper->escape([
            'event' => [
                'full_address' => '<b>Venue</b>, Main St',
                'location_details' => [
                    'venue_name' => '<script>alert(1)</script>',
                    'address_line_1' => '1 Main <i>St</i>',
                ],
            ],
            'event_location' => [
                'name' => '<img src=x>',
                'structured_address' => [
                    'city' => 'Dublin & Co',
                ],
            ],
        ]);

        $this->assertSame('&lt;b&gt;Venue&lt;/b&gt;, Main St', $escaped['event']['full_address']);
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $escaped['event']['location_details']['venue_name']);
        $this->assertSame('1 Main &lt;i&gt;St&lt;/i&gt;', $escaped['event']['location_details']['address_line_1']);
        $this->assertSame('&lt;img src=x&gt;', $escaped['event_location']['name']);
        $this->assertSame('Dublin &amp; Co', $escaped['event_location']['structured_address']['city']);
    }

    public function test_preserves_purified_html_token_values(): void
    {
        $escaped = $this->escaper->escape([
            'event' => [
                'description' => '<p>An <strong>amazing</strong> event</p>',
            ],
            'settings' => [
                'post_checkout_message' => '<strong>Thank you!</strong>',
                'offline_payment_instructions' => '<p>Transfer to IBAN</p>',
            ],
            'event_location' => [
                'online_connection_details' => '<p>Zoom: example.zoom.us/j/123</p>',
            ],
        ]);

        $this->assertSame('<p>An <strong>amazing</strong> event</p>', $escaped['event']['description']);
        $this->assertSame('<strong>Thank you!</strong>', $escaped['settings']['post_checkout_message']);
        $this->assertSame('<p>Transfer to IBAN</p>', $escaped['settings']['offline_payment_instructions']);
        $this->assertSame('<p>Zoom: example.zoom.us/j/123</p>', $escaped['event_location']['online_connection_details']);
    }

    public function test_preserves_non_string_values(): void
    {
        $escaped = $this->escaper->escape([
            'order' => [
                'is_awaiting_offline_payment' => false,
            ],
            'event_location' => [
                'latitude' => 53.3478,
                'structured_address' => null,
            ],
            'cancellation' => [
                'refund_issued' => true,
            ],
        ]);

        $this->assertFalse($escaped['order']['is_awaiting_offline_payment']);
        $this->assertSame(53.3478, $escaped['event_location']['latitude']);
        $this->assertNull($escaped['event_location']['structured_address']);
        $this->assertTrue($escaped['cancellation']['refund_issued']);
    }

    public function test_only_escapes_purified_paths_at_their_exact_position(): void
    {
        $escaped = $this->escaper->escape([
            'attendee' => [
                'description' => '<b>not the event description</b>',
            ],
        ]);

        $this->assertSame('&lt;b&gt;not the event description&lt;/b&gt;', $escaped['attendee']['description']);
    }
}
