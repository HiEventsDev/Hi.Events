<?php

namespace HiEvents\Mail\User;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/api-keys/api-key-created.blade.php
 */
class ApiKeyCreatedMail extends BaseMail
{
    public function __construct(
        private readonly string $keyName,
        private readonly string $accountName,
        private readonly ApiKeyAccess $access,
        private readonly ?string $expiresAt,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('A new API key was created on your account'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.api-keys.api-key-created',
            with: [
                'keyName' => $this->keyName,
                'accountName' => $this->accountName,
                'accessLabel' => $this->access === ApiKeyAccess::READ_WRITE ? __('Read and write') : __('Read-only'),
                'expiresAt' => $this->expiresAt,
                'apiKeysUrl' => Url::getFrontEndUrlFromConfig(Url::API_KEYS),
            ]
        );
    }
}
