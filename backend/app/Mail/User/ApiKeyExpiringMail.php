<?php

namespace HiEvents\Mail\User;

use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/api-keys/api-key-expiring.blade.php
 */
class ApiKeyExpiringMail extends BaseMail
{
    public function __construct(
        private readonly string $keyName,
        private readonly string $accountName,
        private readonly string $expiresAt,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your API key ":keyName" expires soon', ['keyName' => $this->keyName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.api-keys.api-key-expiring',
            with: [
                'keyName' => $this->keyName,
                'accountName' => $this->accountName,
                'expiresAt' => $this->expiresAt,
                'apiKeysUrl' => Url::getFrontEndUrlFromConfig(Url::API_KEYS),
            ]
        );
    }
}
