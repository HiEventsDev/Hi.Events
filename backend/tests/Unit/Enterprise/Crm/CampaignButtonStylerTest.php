<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignButtonStyler;
use Tests\TestCase;

class CampaignButtonStylerTest extends TestCase
{
    public function test_a_button_link_becomes_a_table_based_button_and_other_links_are_left_alone(): void
    {
        $html = (new CampaignButtonStyler)->style('<p><a class="he-button" href="https://example.com/tickets">Get <b>tickets</b></a> and <a href="https://example.com">a link</a></p>');

        $this->assertStringContainsString('<table role="presentation"', $html);
        $this->assertStringContainsString('<a href="https://example.com/tickets" style="display: inline-block;', $html);
        $this->assertStringContainsString('>Get tickets</a>', $html);
        $this->assertStringContainsString('<a href="https://example.com">a link</a>', $html);
    }
}
