<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Mail;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignEventCardDTO;
use HiEvents\Helper\AddressHelper;
use HiEvents\Mail\BaseMail;
use HiEvents\Mail\Concerns\SentOnBehalfOfOrganizer;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * @uses /backend/resources/views/emails/crm/campaign.blade.php
 * @uses /backend/resources/views/emails/crm/campaign-text.blade.php
 */
class CampaignMail extends BaseMail implements SentOnBehalfOfOrganizer
{
    private ?string $preheader = null;

    private ?CampaignEventCardDTO $eventCard = null;

    public function __construct(
        private readonly string $subjectLine,
        ?string $preheader,
        private readonly string $body,
        private readonly string $organizerName,
        private readonly OrganizerDomainObject $organizer,
        private readonly ?string $replyToAddress,
        ?CampaignEventCardDTO $eventCard,
        private readonly ?string $unsubscribeUrl,
        private readonly ?string $oneClickUnsubscribeUrl,
        private readonly bool $isTest,
    ) {
        parent::__construct();

        $this->preheader = $preheader;
        $this->eventCard = $eventCard;
    }

    public function getSubjectLine(): string
    {
        return $this->subjectLine;
    }

    public function getPreheader(): ?string
    {
        return $this->preheader;
    }

    public function getReplyToAddress(): ?string
    {
        return $this->replyToAddress;
    }

    public function getContentOrganizer(): OrganizerDomainObject
    {
        return $this->organizer;
    }

    protected function defaultFrom(): ?Address
    {
        $address = config('mail.campaigns.from.address');

        return $address ? new Address($address, config('mail.campaigns.from.name')) : null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->replyToAddress === null ? [] : [$this->replyToAddress],
            subject: ($this->isTest ? __('[Test]').' ' : '').$this->subjectLine,
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
            markdown: 'emails.crm.campaign',
            text: 'emails.crm.campaign-text',
            with: [
                'preheader' => $this->preheader,
                'body' => $this->body,
                'plainTextBody' => $this->plainTextBody(),
                'organizerName' => $this->organizerName,
                'postalAddress' => $this->postalAddress(),
                'eventCard' => $this->eventCard,
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'isTest' => $this->isTest,
            ],
        );
    }

    private function postalAddress(): ?string
    {
        $address = $this->organizer->getLocationRecord()?->getStructuredAddress();

        if (! is_array($address)) {
            return null;
        }

        $formatted = trim((string) preg_replace('/\s*\R\s*/u', ' ', AddressHelper::formatAddress($address)));

        return $formatted === '' ? null : $formatted;
    }

    private function plainTextBody(): string
    {
        $withInlineUrls = (string) preg_replace_callback(
            '/<a\b[^>]*\bhref="([^"]*)"[^>]*>(.*?)<\/a>/is',
            static function (array $anchor): string {
                $label = trim(strip_tags($anchor[2]));

                return $label === '' || $label === $anchor[1] ? $anchor[1] : $label.' ('.$anchor[1].')';
            },
            $this->body,
        );

        $withLineBreaks = (string) preg_replace(
            ['/<li[^>]*>/i', '/<br\s*\/?>/i', '/<\/(li|tr)>/i', '/<hr[^>]*>|<\/(p|ol|ul|h[1-6]|div|table|blockquote)>/i'],
            ['- ', "\n", "\n", "\n\n"],
            $withInlineUrls,
        );

        $text = html_entity_decode(strip_tags($withLineBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace(['/[ \t]*\n[ \t]*/', '/\n{3,}/'], ["\n", "\n\n"], $text));
    }
}
