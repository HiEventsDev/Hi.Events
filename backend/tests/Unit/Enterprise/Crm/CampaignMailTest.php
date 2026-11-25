<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\Crm\Mail\CampaignMail;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignEventCardDTO;
use Tests\TestCase;

class CampaignMailTest extends TestCase
{
    public function test_a_campaign_email_carries_one_click_unsubscribe_headers_and_the_unsubscribe_link(): void
    {
        $mail = $this->mail(isTest: false);

        $this->assertSame(
            ['List-Unsubscribe' => '<https://api.example.test/public/crm/unsubscribe/42.signature>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'],
            $mail->headers()->text,
        );
        $this->assertSame('Doors open soon', $mail->envelope()->subject);
        $this->assertSame('reply@organizer.test', $mail->envelope()->replyTo[0]->address);

        $html = $mail->render();
        $this->assertStringContainsString('See you there', $html);
        $this->assertStringContainsString('The Jazz &amp; Blues Club', $html);
        $this->assertStringContainsString('href="https://app.example.test/audience/unsubscribe/42.signature"', $html);
        $this->assertStringContainsString('https://app.example.test/event/1/show?promo_code=early-abc', $html);
        $this->assertStringContainsString('Get early access', $html);
    }

    public function test_a_test_email_is_marked_as_a_test_and_has_no_unsubscribe_link_or_headers(): void
    {
        $mail = $this->mail(isTest: true);

        $this->assertSame([], $mail->headers()->text);
        $this->assertSame('[Test] Doors open soon', $mail->envelope()->subject);

        $html = $mail->render();
        $this->assertStringContainsString('See you there', $html);
        $this->assertStringNotContainsString('unsubscribe/', $html);
        $this->assertStringContainsString('Recipients will see an unsubscribe link here.', $html);
    }

    public function test_an_organizer_without_a_reply_address_and_a_campaign_without_an_event_render_cleanly(): void
    {
        $mail = new CampaignMail(
            subjectLine: 'Hello',
            preheader: null,
            body: '<p>Plain</p>',
            organizerName: '<script>alert(1)</script>',
            organizer: (new OrganizerDomainObject)->setId(4)->setAccountId(7)->setName('<script>alert(1)</script>'),
            replyToAddress: null,
            eventCard: null,
            unsubscribeUrl: 'https://app.example.test/audience/unsubscribe/1.s',
            oneClickUnsubscribeUrl: 'https://api.example.test/public/crm/unsubscribe/1.s',
            isTest: false,
        );

        $this->assertSame([], $mail->envelope()->replyTo);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $mail->render());
        $this->assertStringNotContainsString('promo_code', $mail->render());
    }

    public function test_the_footer_shows_the_organizers_postal_address_when_it_has_one(): void
    {
        $organizer = (new OrganizerDomainObject)->setId(4)->setAccountId(7)->setName('The Jazz & Blues Club')
            ->setLocationRecord((new LocationDomainObject)->setStructuredAddress([
                'address_line_1' => '12 <b>Quay</b> Street',
                'address_line_2' => '',
                'city' => 'Dublin',
                'zip_or_postal_code' => 'D02 X285',
                'country' => 'IE',
            ]));

        $mail = $this->mail(isTest: false, organizer: $organizer);

        $mail->assertSeeInHtml('12 &lt;b&gt;Quay&lt;/b&gt; Street, Dublin, D02 X285, IE', false);
        $mail->assertDontSeeInHtml('<b>Quay</b>', false);
        $mail->assertSeeInOrderInText([
            'You are receiving this email because you opted in to hear from The Jazz & Blues Club.',
            '12 <b>Quay</b> Street, Dublin, D02 X285, IE',
            'Unsubscribe: https://app.example.test/audience/unsubscribe/42.signature',
        ]);
    }

    public function test_the_footer_has_no_postal_address_when_the_organizer_has_none(): void
    {
        $withoutLocation = $this->mail(isTest: false);
        $withEmptyAddress = $this->mail(
            isTest: false,
            organizer: (new OrganizerDomainObject)->setId(4)->setAccountId(7)->setName('The Jazz & Blues Club')
                ->setLocationRecord((new LocationDomainObject)->setStructuredAddress(['address_line_1' => '', 'city' => null])),
        );

        foreach ([$withoutLocation, $withEmptyAddress] as $mail) {
            $mail->assertSeeInOrderInText([
                'You are receiving this email because you opted in to hear from The Jazz & Blues Club.',
                'Unsubscribe: https://app.example.test/audience/unsubscribe/42.signature',
            ]);
            $this->assertStringNotContainsString('<br>', $mail->render());
        }
    }

    private function mail(bool $isTest, ?OrganizerDomainObject $organizer = null): CampaignMail
    {
        return new CampaignMail(
            subjectLine: 'Doors open soon',
            preheader: 'Tickets are going fast',
            body: '<p>See you there</p>',
            organizerName: 'The Jazz & Blues Club',
            organizer: $organizer ?? (new OrganizerDomainObject)->setId(4)->setAccountId(7)->setName('The Jazz & Blues Club'),
            replyToAddress: 'reply@organizer.test',
            eventCard: new CampaignEventCardDTO(
                title: 'Spring Show',
                date_line: 'Sat, Jun 5, 2027 8:00 PM',
                location_line: 'Dockside Hall, Dublin',
                image_url: null,
                url: 'https://app.example.test/event/1/show?promo_code=early-abc',
                button_label: 'Get early access',
            ),
            unsubscribeUrl: $isTest ? null : 'https://app.example.test/audience/unsubscribe/42.signature',
            oneClickUnsubscribeUrl: $isTest ? null : 'https://api.example.test/public/crm/unsubscribe/42.signature',
            isTest: $isTest,
        );
    }
}
