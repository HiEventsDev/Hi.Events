<?php

namespace HiEvents\Mail;

use HiEvents\Services\Domain\Mail\DTO\MailBrandingDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

abstract class BaseMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected ?MailBrandingDTO $branding = null;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function withBranding(?MailBrandingDTO $branding): static
    {
        $this->branding = $branding;

        return $this;
    }

    public function buildViewData(): array
    {
        view()->share([
            'hideBranding' => $this->branding?->hideBranding ?? false,
            'organizerLogoUrl' => $this->branding?->organizerLogoUrl,
            'organizerName' => $this->branding?->organizerName,
        ]);

        return parent::buildViewData();
    }

    abstract public function envelope(): Envelope;

    abstract public function content(): Content;
}
