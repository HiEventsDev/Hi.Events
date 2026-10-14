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
 * @uses /backend/resources/views/emails/lifecycle/nudge-text.blade.php
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
            replyTo: config('mail.lifecycle.reply_to.address') === null ? [] : [
                new Address(
                    config('mail.lifecycle.reply_to.address'),
                    config('mail.lifecycle.reply_to.name'),
                ),
            ],
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
            view: 'emails.lifecycle.nudge',
            text: 'emails.lifecycle.nudge-text',
            with: [
                'renderedBody' => $this->renderedBody,
                'plainTextBody' => $this->toPlainText($this->renderedBody),
                'ctaLabel' => $this->ctaLabel,
                'ctaUrl' => $this->ctaUrl,
                'signatureName' => config('mail.lifecycle.signature.name'),
                'signatureTitle' => config('mail.lifecycle.signature.title'),
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }

    private function toPlainText(string $html): string
    {
        $text = preg_replace(
            ['/\s+/', '/<li[^>]*>/i', '/<br\s*\/?>/i', '/<\/li>/i', '/<\/(p|ol|ul|h[1-6]|div)>/i'],
            [' ', '- ', "\n", "\n", "\n\n"],
            $html,
        );

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace(['/[ \t]*\n[ \t]*/', '/\n{3,}/'], ["\n", "\n\n"], $text);

        return trim($text);
    }
}
