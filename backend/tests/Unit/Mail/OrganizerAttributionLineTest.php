<?php

namespace Tests\Unit\Mail;

use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\Crm\Mail\CampaignMail;
use HiEvents\Mail\BaseMail;
use HiEvents\Mail\Event\EventMessage;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Domain\Email\Delivery\EmailDeliveryRecorder;
use HiEvents\Services\Domain\Mail\DTO\MailBrandingDTO;
use HiEvents\Services\Domain\Mail\MailBrandingService;
use HiEvents\Services\Domain\Mail\UnpublishedEventMailPolicy;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class OrganizerAttributionLineTest extends TestCase
{
    private const LINE = 'This message is from Acme Events, not from';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => true]);

        $this->app->instance(EmailDeliveryRecorder::class, Mockery::mock(EmailDeliveryRecorder::class)->shouldIgnoreMissing());

        $policy = Mockery::mock(UnpublishedEventMailPolicy::class);
        $policy->shouldReceive('shouldWithholdEventContent')->andReturnFalse();
        $this->app->instance(UnpublishedEventMailPolicy::class, $policy);
    }

    #[DataProvider('organizerMail')]
    public function test_accounts_with_hi_events_branding_are_told_who_the_message_is_from(string $mail): void
    {
        $message = $this->send($this->{$mail}(), hideHiEventsBranding: false);

        $this->assertStringContainsString(self::LINE, $message->getHtmlBody());
        $this->assertStringContainsString(self::LINE, $message->getTextBody());
    }

    #[DataProvider('organizerMail')]
    public function test_accounts_that_paid_to_remove_branding_send_without_the_line(string $mail): void
    {
        $message = $this->send($this->{$mail}(), hideHiEventsBranding: true);

        $this->assertStringNotContainsString('not from', $message->getHtmlBody());
        $this->assertStringNotContainsString('not from', $message->getTextBody());
    }

    public static function organizerMail(): array
    {
        return [
            'event message' => ['eventMessage'],
            'campaign' => ['campaign'],
        ];
    }

    private function send(BaseMail $mail, bool $hideHiEventsBranding): Email
    {
        $brandingService = Mockery::mock(MailBrandingService::class);
        $brandingService->shouldReceive('forMail')->andReturn(new MailBrandingDTO(
            hideHiEventsBranding: $hideHiEventsBranding,
            showHiEventsPromo: false,
            promoUrl: 'https://hi.events',
            organizerName: 'Acme Events',
        ));
        $this->app->instance(MailBrandingService::class, $brandingService);

        return Mail::mailer('array')->to('buyer@example.test')->sendNow($mail)->getOriginalMessage();
    }

    private function organizer(): OrganizerDomainObject
    {
        return (new OrganizerDomainObject)->setId(4)->setAccountId(7)->setName('Acme Events');
    }

    private function eventMessage(): EventMessage
    {
        return new EventMessage(
            event: (new EventDomainObject)->setId(10)->setTitle('Jazz Nights')->setOrganizer($this->organizer()),
            eventSettings: (new EventSettingDomainObject)->setSupportEmail('support@acme.test'),
            messageData: new SendMessageDTO(
                account_id: 7,
                event_id: 10,
                subject: 'Doors open at 7',
                message: '<p>See you there</p>',
                type: MessageTypeEnum::ALL_ATTENDEES,
                is_test: false,
                send_copy_to_current_user: false,
                sent_by_user_id: 1,
            ),
        );
    }

    private function campaign(): CampaignMail
    {
        return new CampaignMail(
            subjectLine: 'Spring season',
            preheader: null,
            body: '<p>Tickets are on sale</p>',
            organizerName: 'Acme Events',
            organizer: $this->organizer(),
            replyToAddress: null,
            eventCard: null,
            unsubscribeUrl: null,
            oneClickUnsubscribeUrl: null,
            isTest: false,
        );
    }
}
