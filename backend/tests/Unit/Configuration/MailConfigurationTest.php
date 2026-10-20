<?php

namespace Tests\Unit\Configuration;

use Illuminate\Mail\MailManager;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\TestCase;

class MailConfigurationTest extends TestCase
{
    public function test_smtp_transport_can_disable_opportunistic_starttls(): void
    {
        config([
            'mail.mailers.smtp' => array_merge(config('mail.mailers.smtp'), [
                'auto_tls' => false,
                'verify_peer' => false,
            ]),
        ]);

        /** @var MailManager $mailManager */
        $mailManager = app('mail.manager');

        $transport = $mailManager->createSymfonyTransport(config('mail.mailers.smtp'));

        $this->assertInstanceOf(EsmtpTransport::class, $transport);
        $this->assertFalse($transport->isAutoTls());
        $this->assertFalse($transport->getStream()->getStreamOptions()['ssl']['verify_peer']);
        $this->assertFalse($transport->getStream()->getStreamOptions()['ssl']['verify_peer_name']);
    }
}
