<?php

declare(strict_types=1);

namespace HiEvents\Mail\Lifecycle;

use HiEvents\DomainObjects\Enums\LifecycleNudgeType;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * @uses /backend/resources/views/emails/lifecycle/nudge.blade.php
 */
class LifecycleNudgeMail extends Mailable
{
    public function __construct(
        private readonly LifecycleNudgeType $nudgeType,
        private readonly string $renderedSubject,
        private readonly string $renderedBody,
        private readonly string $ctaLabel,
        private readonly string $ctaUrl,
        private readonly string $unsubscribeUrl,
        private readonly ?string $oneClickUnsubscribeUrl = null,
        private readonly ?int $accountId = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.lifecycle.from.address'),
                config('mail.lifecycle.from.name'),
            ),
            subject: $this->renderedSubject,
            tags: [strtolower($this->nudgeType->name)],
            metadata: $this->accountId === null ? [] : ['account_id' => (string) $this->accountId],
        );
    }

    public function headers(): Headers
    {
        if ($this->oneClickUnsubscribeUrl === null) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->oneClickUnsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.lifecycle.nudge',
            with: [
                'renderedBody' => $this->renderedBody,
                'ctaLabel' => $this->ctaLabel,
                'ctaUrl' => $this->ctaUrl,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }
}
