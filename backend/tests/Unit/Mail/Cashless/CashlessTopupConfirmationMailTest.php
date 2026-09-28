<?php

namespace Tests\Unit\Mail\Cashless;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\Cashless\CashlessTopupConfirmationMail;
use HiEvents\Services\Domain\Email\DTO\RenderedEmailTemplateDTO;
use Tests\TestCase;

class CashlessTopupConfirmationMailTest extends TestCase
{
    public function test_it_uses_the_default_view_and_subject_without_a_custom_template(): void
    {
        $mail = $this->makeMail();

        $this->assertSame('emails.cashless.topup-confirmation', $mail->content()->markdown);
        $this->assertSame('💳 Your cashless balance for Summer Fest', $mail->envelope()->subject);
    }

    public function test_default_view_shows_the_balance_without_layout_artifacts(): void
    {
        $html = $this->makeMail()->render();

        $this->assertStringContainsString('$35.00', $html);
        $this->assertStringContainsString('$20.00', $html);
        $this->assertStringContainsString('https://example.com/cashless/1/a_abc', $html);
        $this->assertStringNotContainsString('<x-mail', $html);
    }

    public function test_it_uses_the_custom_template_layout_when_one_is_rendered(): void
    {
        $mail = $this->makeMail(new RenderedEmailTemplateDTO(
            subject: 'Custom subject',
            body: '<p>Custom body</p>',
            cta: ['label' => 'View my balance', 'url' => 'https://example.com/cashless/1/a_abc'],
        ));

        $content = $mail->content();

        $this->assertSame('emails.custom-template', $content->markdown);
        $this->assertSame('<p>Custom body</p>', $content->with['renderedBody']);
        $this->assertSame('Custom subject', $mail->envelope()->subject);
    }

    private function makeMail(?RenderedEmailTemplateDTO $renderedTemplate = null): CashlessTopupConfirmationMail
    {
        return new CashlessTopupConfirmationMail(
            attendee: (new AttendeeDomainObject)->setFirstName('Marie')->setLastName('Durand'),
            event: (new EventDomainObject)->setId(1)->setTitle('Summer Fest'),
            eventSettings: (new EventSettingDomainObject)->setSupportEmail('support@example.com'),
            organizer: (new OrganizerDomainObject)->setName('Organizer'),
            toppedUpAmount: '$20.00',
            newBalance: '$35.00',
            walletUrl: 'https://example.com/cashless/1/a_abc',
            renderedTemplate: $renderedTemplate,
        );
    }
}
