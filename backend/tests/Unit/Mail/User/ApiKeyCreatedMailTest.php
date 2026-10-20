<?php

namespace Tests\Unit\Mail\User;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\Mail\User\ApiKeyCreatedMail;
use Tests\TestCase;

class ApiKeyCreatedMailTest extends TestCase
{
    public function test_panel_renders_as_markup_and_closes_before_the_rest_of_the_email(): void
    {
        $html = (new ApiKeyCreatedMail(
            keyName: 'Zapier sync',
            accountName: 'Acme Events',
            access: ApiKeyAccess::READ_WRITE,
            expiresAt: null,
        ))->render();

        $this->assertStringNotContainsString('&lt;/td&gt;', $html);
        $this->assertStringNotContainsString('&lt;/table&gt;', $html);
        $this->assertStringNotContainsString('<pre', $html);
        $this->assertStringNotContainsString('<code', $html);

        $panelStart = strpos($html, 'class="panel"');
        $panelItemEnd = strpos($html, '</td>', strpos($html, 'class="panel-item"'));
        $button = strpos($html, 'class="action"');

        $this->assertNotFalse($panelStart);
        $this->assertStringContainsString('Zapier sync', substr($html, $panelStart, $panelItemEnd - $panelStart));
        $this->assertGreaterThan($panelItemEnd, $button);
        $this->assertSame(
            substr_count($html, '<table'),
            substr_count($html, '</table>'),
        );
    }
}
