<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\Crm\Mail\CampaignMail;
use HiEvents\Mail\BaseMail;
use HiEvents\Services\Domain\Mail\DTO\MailBrandingDTO;
use HiEvents\Services\Domain\Mail\MailBrandingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Symfony\Component\Mime\Address;
use Tests\TestCase;

class CampaignMailFromTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'array',
            'mail.from' => ['address' => 'tickets@platform.test', 'name' => 'Platform'],
            'mail.campaigns.mailer' => null,
        ]);
    }

    public function test_campaigns_are_sent_from_the_campaign_address_when_one_is_configured(): void
    {
        config(['mail.campaigns.from' => ['address' => 'news@mail.platform.test', 'name' => 'Platform News']]);
        $this->brandWith(senderName: null);

        $this->assertEquals([new Address('news@mail.platform.test', 'Platform News')], $this->sentFrom($this->campaignMail()));
    }

    public function test_a_branded_campaign_keeps_the_organizer_name_on_the_campaign_address(): void
    {
        config(['mail.campaigns.from' => ['address' => 'news@mail.platform.test', 'name' => 'Platform News']]);
        $this->brandWith(senderName: 'The Jazz Club');

        $this->assertEquals([new Address('news@mail.platform.test', 'The Jazz Club')], $this->sentFrom($this->campaignMail()));
    }

    public function test_campaigns_fall_back_to_the_platform_address_when_no_campaign_address_is_configured(): void
    {
        config(['mail.campaigns.from' => ['address' => null, 'name' => 'Platform']]);
        $this->brandWith(senderName: 'The Jazz Club');

        $this->assertEquals([new Address('tickets@platform.test', 'The Jazz Club')], $this->sentFrom($this->campaignMail()));
    }

    private function brandWith(?string $senderName): void
    {
        $branding = Mockery::mock(MailBrandingService::class);
        $branding->shouldReceive('forMail')->andReturn(new MailBrandingDTO(
            hideHiEventsBranding: false,
            showHiEventsPromo: false,
            promoUrl: 'https://platform.test',
            senderName: $senderName,
        ));
        $this->app->instance(MailBrandingService::class, $branding);
    }

    /**
     * @return Address[]
     */
    private function sentFrom(BaseMail $mail): array
    {
        $mail->to('buyer@example.test');
        $mail->send(Mail::mailer('array'));

        return Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage()->getFrom();
    }

    private function campaignMail(): CampaignMail
    {
        return new CampaignMail(
            subjectLine: 'Doors open soon',
            preheader: null,
            body: '<p>See you there</p>',
            organizerName: 'The Jazz Club',
            organizer: (new OrganizerDomainObject)->setId(4)->setAccountId(7)->setName('The Jazz Club'),
            replyToAddress: null,
            eventCard: null,
            unsubscribeUrl: 'https://app.example.test/audience/unsubscribe/42.signature',
            oneClickUnsubscribeUrl: 'https://api.example.test/public/crm/unsubscribe/42.signature',
            isTest: false,
        );
    }
}
