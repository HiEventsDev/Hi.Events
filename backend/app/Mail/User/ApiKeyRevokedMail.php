<?php

namespace HiEvents\Mail\User;

use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/api-keys/api-key-revoked.blade.php
 */
class ApiKeyRevokedMail extends BaseMail
{
    public function __construct(
        private readonly string $keyName,
        private readonly string $accountName,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('One of your API keys was revoked'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.api-keys.api-key-revoked',
            with: [
                'keyName' => $this->keyName,
                'accountName' => $this->accountName,
                'apiKeysUrl' => Url::getFrontEndUrlFromConfig(Url::API_KEYS),
            ]
        );
    }
}
