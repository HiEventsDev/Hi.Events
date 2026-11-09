<?php

namespace HiEvents\Mail\User;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/oauth/app-connected.blade.php
 */
class OAuthAppConnectedMail extends BaseMail
{
    public function __construct(
        private readonly string $appName,
        private readonly string $redirectHost,
        private readonly string $accountName,
        private readonly ApiKeyAccess $access,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('A new app was connected to your account'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.oauth.app-connected',
            with: [
                'appName' => $this->appName,
                'redirectHost' => $this->redirectHost,
                'accountName' => $this->accountName,
                'accessLabel' => $this->access === ApiKeyAccess::READ_WRITE ? __('Read and write') : __('Read-only'),
                'connectedAppsUrl' => Url::getFrontEndUrlFromConfig(Url::API_KEYS),
            ]
        );
    }
}
