<?php

namespace Tests\Feature\Services\Domain\Email;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Services\Domain\Email\EmailTemplateService;
use Tests\TestCase;

class DefaultTemplateLineupBlockTest extends TestCase
{
    public function test_default_order_and_ticket_templates_render_the_lineup_block(): void
    {
        $service = $this->app->make(EmailTemplateService::class);

        foreach ([EmailTemplateType::ORDER_CONFIRMATION, EmailTemplateType::ATTENDEE_TICKET] as $type) {
            $default = $service->getDefaultTemplate($type);
            $body = $service->previewTemplate($default['subject'], $default['body'], $type)['body'];

            $this->assertStringContainsString('Velvet Harbour', $body);
            $this->assertStringContainsString('Main Stage', $body);
            $this->assertStringContainsString('See the full schedule', $body);
            $this->assertStringNotContainsString('{{', $body);
            $this->assertStringNotContainsString('{%', $body);
        }
    }
}
