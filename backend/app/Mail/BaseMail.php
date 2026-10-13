<?php

namespace HiEvents\Mail;

use HiEvents\Mail\Concerns\ContainsEventContent;
use HiEvents\Services\Domain\Mail\UnpublishedEventMailPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\SentMessage;
use Illuminate\Queue\SerializesModels;

abstract class BaseMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct()
    {
        $this->afterCommit();
    }

    abstract public function envelope(): Envelope;

    abstract public function content(): Content;

    public function send($mailer): ?SentMessage
    {
        if ($this instanceof ContainsEventContent
            && app(UnpublishedEventMailPolicy::class)->shouldWithholdEventContent(
                $this->getContentEvent(),
                $this->allRecipientAddresses(),
            )) {
            $replacement = $this->withheldEventContentReplacement()
                ->to($this->asRecipients($this->to))
                ->cc($this->asRecipients($this->cc))
                ->bcc($this->asRecipients($this->bcc))
                ->locale($this->locale);

            if ($this->mailer !== null) {
                $replacement->mailer($this->mailer);
            }

            return $replacement->send($mailer);
        }

        return parent::send($mailer);
    }

    private function asRecipients(array $addresses): array
    {
        return array_map(
            fn (array $recipient) => ['name' => $recipient['name'] ?? null, 'email' => $recipient['address']],
            $addresses,
        );
    }

    /**
     * @return string[]
     */
    private function allRecipientAddresses(): array
    {
        return collect([...$this->to, ...$this->cc, ...$this->bcc])
            ->pluck('address')
            ->filter()
            ->values()
            ->all();
    }
}
