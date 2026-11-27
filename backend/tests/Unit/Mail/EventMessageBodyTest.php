<?php

namespace Tests\Unit\Mail;

use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\Event\EventMessage;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Domain\Email\Delivery\EmailDeliveryRecorder;
use HiEvents\Services\Domain\Mail\DTO\MailBrandingDTO;
use HiEvents\Services\Domain\Mail\MailBrandingService;
use HiEvents\Services\Domain\Mail\UnpublishedEventMailPolicy;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class EventMessageBodyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(EmailDeliveryRecorder::class, Mockery::mock(EmailDeliveryRecorder::class)->shouldIgnoreMissing());

        $policy = Mockery::mock(UnpublishedEventMailPolicy::class);
        $policy->shouldReceive('shouldWithholdEventContent')->andReturnFalse();
        $this->app->instance(UnpublishedEventMailPolicy::class, $policy);

        $brandingService = Mockery::mock(MailBrandingService::class);
        $brandingService->shouldReceive('forMail')->andReturn(new MailBrandingDTO(
            hideHiEventsBranding: false,
            showHiEventsPromo: false,
            promoUrl: 'https://hi.events',
            organizerName: 'Acme Events',
        ));
        $this->app->instance(MailBrandingService::class, $brandingService);
    }

    public function test_markdown_typed_into_a_message_is_sent_as_text_and_never_becomes_an_image_or_a_link(): void
    {
        $html = $this->sentHtml("<p>See you Saturday</p>\n\n![](https://tracker.example/qr.png)\n\n[Refund](https\\://phish.example/pay)\n\n# Urgent");

        $this->assertStringContainsString('See you Saturday</p> ![](https://tracker.example/qr.png) [Refund](https\\://phish.example/pay) # Urgent</div>', $html);
        $this->assertStringNotContainsString('tracker.example/qr.png"', $html);
        $this->assertStringNotContainsString('href="https://phish.example', $html);
        $this->assertStringNotContainsString('Urgent</h1>', $html);
    }

    public function test_the_html_an_organizer_wrote_is_sent_as_html(): void
    {
        $html = $this->sentHtml("<p>Doors at <strong>7pm</strong></p>\n<p><a href=\"https://example.com/map\">Map</a></p>");

        $this->assertMatchesRegularExpression('#<strong[^>]*>7pm</strong></p>\s*<p[^>]*><a href="https://example\.com/map"[^>]*>Map</a>#', $html);
    }

    private function sentHtml(string $message): string
    {
        $mail = new EventMessage(
            event: (new EventDomainObject)->setId(10)->setTitle('Jazz Nights')->setOrganizer((new OrganizerDomainObject)->setId(4)->setAccountId(7)->setName('Acme Events')),
            eventSettings: (new EventSettingDomainObject)->setSupportEmail('support@acme.test'),
            messageData: new SendMessageDTO(
                account_id: 7,
                event_id: 10,
                subject: 'Doors open at 7',
                message: $message,
                type: MessageTypeEnum::ALL_ATTENDEES,
                is_test: false,
                send_copy_to_current_user: false,
                sent_by_user_id: 1,
            ),
        );

        return Mail::mailer('array')->to('buyer@example.test')->sendNow($mail)->getOriginalMessage()->getHtmlBody();
    }
}
