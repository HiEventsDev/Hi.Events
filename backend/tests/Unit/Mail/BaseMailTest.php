<?php

namespace Tests\Unit\Mail;

use HiEvents\Mail\BaseMail;
use HiEvents\Services\Domain\Mail\DTO\MailBrandingDTO;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Tests\TestCase;

class BaseMailTest extends TestCase
{
    private function makeMail(): BaseMail
    {
        return new class extends BaseMail
        {
            public function envelope(): Envelope
            {
                return new Envelope(subject: 'Test');
            }

            public function content(): Content
            {
                return new Content(markdown: 'emails.orders.order-cancelled');
            }
        };
    }

    public function test_build_view_data_shares_branding_with_all_views(): void
    {
        $mail = $this->makeMail()->withBranding(new MailBrandingDTO(
            hideBranding: true,
            organizerLogoUrl: 'https://cdn.example.com/logo.png',
            organizerName: 'Acme Events',
        ));

        $mail->buildViewData();

        $this->assertTrue(view()->shared('hideBranding'));
        $this->assertSame('https://cdn.example.com/logo.png', view()->shared('organizerLogoUrl'));
        $this->assertSame('Acme Events', view()->shared('organizerName'));
    }

    public function test_build_view_data_resets_branding_when_none_attached(): void
    {
        $this->makeMail()
            ->withBranding(new MailBrandingDTO(hideBranding: true, organizerLogoUrl: 'x', organizerName: 'y'))
            ->buildViewData();

        $this->makeMail()->buildViewData();

        $this->assertFalse(view()->shared('hideBranding'));
        $this->assertNull(view()->shared('organizerLogoUrl'));
        $this->assertNull(view()->shared('organizerName'));
    }
}
