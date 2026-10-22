<?php

namespace Tests\Unit\Mail;

use Closure;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\Order\OrderSummary;
use HiEvents\Services\Domain\Email\Delivery\EmailDeliveryRecorder;
use HiEvents\Services\Domain\Mail\DTO\MailBrandingDTO;
use HiEvents\Services\Domain\Mail\MailBrandingService;
use HiEvents\Services\Domain\Mail\UnpublishedEventMailPolicy;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\Concerns\ManagesLicence;
use Tests\TestCase;

class MailBrandingLayoutTest extends TestCase
{
    use ManagesLicence;

    private const PROMO_URL = 'https://hi.events?utm_source=app-email-promo';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLicenceKey(null);

        $this->app->instance(EmailDeliveryRecorder::class, Mockery::mock(EmailDeliveryRecorder::class)->shouldIgnoreMissing());
    }

    public function test_the_promo_sits_above_the_powered_by_footer(): void
    {
        $html = $this->render(new MailBrandingDTO(
            hideHiEventsBranding: false,
            showHiEventsPromo: true,
            promoUrl: self::PROMO_URL,
        ));

        $this->assertStringContainsString('Planning an event of your own?', $html);
        $this->assertStringContainsString('Sell tickets and manage attendees with '.config('app.name').'.', $html);
        $this->assertStringContainsString('href="'.self::PROMO_URL.'"', $html);
        $this->assertStringContainsString('Powered by', $html);
        $this->assertStringContainsString('hi-events-stacked-light.png', $html);
    }

    public function test_without_the_promo_only_the_powered_by_footer_shows(): void
    {
        $html = $this->render(new MailBrandingDTO(
            hideHiEventsBranding: false,
            showHiEventsPromo: false,
            promoUrl: self::PROMO_URL,
        ));

        $this->assertStringNotContainsString('Planning an event of your own?', $html);
        $this->assertStringContainsString('Powered by', $html);
    }

    public function test_subscribed_organizers_replace_hi_events_branding_with_their_own(): void
    {
        $html = $this->render(new MailBrandingDTO(
            hideHiEventsBranding: true,
            showHiEventsPromo: false,
            promoUrl: self::PROMO_URL,
            organizerName: 'Acme Events',
            organizerLogoUrl: 'https://cdn.test/acme-logo.png',
            organizerUrl: 'https://acme.test',
        ));

        $this->assertStringContainsString('src="https://cdn.test/acme-logo.png"', $html);
        $this->assertStringContainsString('href="https://acme.test"', $html);
        $this->assertStringContainsString('© '.date('Y').' Acme Events', $html);
        $this->assertStringNotContainsString('hi-events-stacked-light.png', $html);
        $this->assertStringNotContainsString('Powered by', $html);
        $this->assertStringNotContainsString('Planning an event of your own?', $html);
    }

    public function test_subscribed_organizers_without_a_logo_or_website_show_their_name_unlinked(): void
    {
        $html = $this->render(new MailBrandingDTO(
            hideHiEventsBranding: true,
            showHiEventsPromo: false,
            promoUrl: self::PROMO_URL,
            organizerName: 'Acme Events',
        ));

        $this->assertMatchesRegularExpression('/<td class="header"[^>]*>\s*<span class="organizer-name"[^>]*>Acme Events<\/span>/', $html);
    }

    public function test_subscribers_send_under_the_organizer_name_in_their_brand_colour(): void
    {
        $message = $this->send(new MailBrandingDTO(
            hideHiEventsBranding: true,
            showHiEventsPromo: false,
            promoUrl: self::PROMO_URL,
            organizerName: 'Acme Events',
            senderName: 'Acme Events',
            buttonColor: '#1b36f5',
            buttonTextColor: '#ffffff',
            linkColor: '#1b36f5',
        ));

        $from = $message->getOriginalMessage()->getFrom()[0];
        $html = $message->getOriginalMessage()->getHtmlBody();

        $this->assertSame('Acme Events', $from->getName());
        $this->assertSame(config('mail.from.address'), $from->getAddress());
        $this->assertStringContainsString('background-color: #1b36f5; color: #ffffff; box-shadow: none;', $html);
        $this->assertMatchesRegularExpression('/<a href="mailto:organizer@acme\.test"[^>]*style="[^"]*color: #1b36f5/', $html);
    }

    public function test_a_sender_set_by_the_mailable_is_never_replaced(): void
    {
        $message = $this->send(new MailBrandingDTO(
            hideHiEventsBranding: true,
            showHiEventsPromo: false,
            promoUrl: self::PROMO_URL,
            organizerName: 'Acme Events',
            senderName: 'Acme Events',
        ), fn ($mail) => $mail->from('team@example.test', 'Example Team'));

        $from = $message->getOriginalMessage()->getFrom()[0];

        $this->assertSame('Example Team', $from->getName());
        $this->assertSame('team@example.test', $from->getAddress());
    }

    public function test_without_a_sender_name_the_default_sender_is_kept(): void
    {
        $message = $this->send(new MailBrandingDTO(
            hideHiEventsBranding: false,
            showHiEventsPromo: true,
            promoUrl: self::PROMO_URL,
        ));

        $this->assertSame(config('mail.from.name'), $message->getOriginalMessage()->getFrom()[0]->getName());
        $this->assertStringNotContainsString('.content-cell a:not(.button)', $message->getOriginalMessage()->getHtmlBody());
    }

    public function test_branding_does_not_leak_into_the_next_mail(): void
    {
        $this->render(new MailBrandingDTO(
            hideHiEventsBranding: true,
            showHiEventsPromo: false,
            promoUrl: self::PROMO_URL,
            organizerName: 'Acme Events',
        ));

        $this->assertNull(view()->shared('mailBranding'));
    }

    private function render(MailBrandingDTO $branding): string
    {
        return $this->send($branding)->getOriginalMessage()->getHtmlBody();
    }

    private function send(MailBrandingDTO $branding, ?Closure $prepare = null): SentMessage
    {
        $brandingService = Mockery::mock(MailBrandingService::class);
        $brandingService->shouldReceive('forMail')->andReturn($branding);
        $this->app->instance(MailBrandingService::class, $brandingService);

        $policy = Mockery::mock(UnpublishedEventMailPolicy::class);
        $policy->shouldReceive('shouldWithholdEventContent')->andReturnFalse();
        $this->app->instance(UnpublishedEventMailPolicy::class, $policy);

        $mail = new OrderSummary(
            order: (new OrderDomainObject)
                ->setId(30)
                ->setShortId('o_short')
                ->setPublicId('ORD-1')
                ->setTotalGross(0)
                ->setStatus(OrderStatus::COMPLETED->name)
                ->setOrderItems(collect()),
            event: (new EventDomainObject)
                ->setId(10)
                ->setTitle('Jazz Nights')
                ->setTimezone('UTC')
                ->setCurrency('USD')
                ->setEventOccurrences(collect([
                    (new EventOccurrenceDomainObject)->setId(2)->setStartDate('2026-11-12 18:00:00'),
                ])),
            organizer: (new OrganizerDomainObject)->setName('Acme Events')->setEmail('organizer@acme.test'),
            eventSettings: (new EventSettingDomainObject)->setSupportEmail('support@acme.test'),
            invoice: null,
        );

        if ($prepare !== null) {
            $prepare($mail);
        }

        return Mail::mailer('array')->to('buyer@example.test')->sendNow($mail);
    }
}
