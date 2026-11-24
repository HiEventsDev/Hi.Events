<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\Crm\Mail\CampaignMail;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignEventCardDTO;
use Tests\TestCase;

class CampaignMailPlainTextTest extends TestCase
{
    public function test_the_plain_text_part_keeps_the_unsubscribe_link_and_every_other_link(): void
    {
        config(['app.name' => 'Acme Tickets', 'mail.from.address' => 'abuse@acme.test', 'app.saas_mode_enabled' => true]);

        $mail = $this->mail(
            body: '<h2>What&#039;s new</h2><p>Hi Ada &amp; friends,</p><p>Read <a href="https://example.test/news?a=1&amp;b=2">our news</a>.</p>'
                .'<ul><li>First</li><li>Second</li></ul>'
                .'<table role="presentation"><tr><td><a href="https://example.test/book" style="color: #ffffff;">Book now</a></td></tr></table>',
            isTest: false,
        );

        $mail->assertSeeInOrderInText([
            'This message is from The Jazz & Blues Club, not from Acme Tickets.',
            "What's new",
            'Hi Ada & friends,',
            'Read our news (https://example.test/news?a=1&b=2).',
            '- First',
            '- Second',
            'Book now (https://example.test/book)',
            'Spring Show',
            'Sat, Jun 5, 2027 8:00 PM',
            'Dockside Hall, Dublin',
            'Get early access: https://app.example.test/event/1/show?promo_code=early-abc',
            'You are receiving this email because you opted in to hear from The Jazz & Blues Club.',
            'Unsubscribe: https://app.example.test/audience/unsubscribe/42.signature',
            'abuse@acme.test',
            'Acme Tickets',
        ]);
        $mail->assertDontSeeInText('<');
        $mail->assertDontSeeInText('&amp;');
        $mail->assertSeeInHtml('href="https://app.example.test/audience/unsubscribe/42.signature"', false);
    }

    public function test_a_platform_hosting_many_organizers_says_the_email_is_not_from_the_platform(): void
    {
        config(['app.name' => 'Acme Tickets', 'app.saas_mode_enabled' => true]);

        $mail = $this->mail(body: '<p>See you there</p>', isTest: false);

        $mail->assertSeeInHtml('This message is from The Jazz &amp; Blues Club, not from Acme Tickets.', false);
        $mail->assertSeeInText('This message is from The Jazz & Blues Club, not from Acme Tickets.');
    }

    public function test_a_self_hosted_install_sends_the_email_without_the_not_from_the_platform_line(): void
    {
        config(['app.name' => 'Acme Tickets', 'app.saas_mode_enabled' => false]);

        $mail = $this->mail(body: '<p>See you there</p>', isTest: false);

        $mail->assertDontSeeInHtml('not from Acme Tickets');
        $mail->assertDontSeeInText('not from Acme Tickets');
        $mail->assertSeeInOrderInText(['See you there', 'You are receiving this email because you opted in to hear from The Jazz & Blues Club.']);
        $mail->assertSeeInHtml('Powered by');
    }

    public function test_the_plain_text_part_of_a_test_email_has_no_unsubscribe_link(): void
    {
        $mail = $this->mail(body: '<p>See you there</p>', isTest: true);

        $mail->assertSeeInText('See you there');
        $mail->assertSeeInText('Recipients will see an unsubscribe link here.');
        $mail->assertDontSeeInText('unsubscribe/');
    }

    private function mail(string $body, bool $isTest): CampaignMail
    {
        return new CampaignMail(
            subjectLine: 'Doors open soon',
            preheader: 'Tickets are going fast',
            body: $body,
            organizerName: 'The Jazz & Blues Club',
            organizer: (new OrganizerDomainObject)->setId(4)->setAccountId(7)->setName('The Jazz & Blues Club'),
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
