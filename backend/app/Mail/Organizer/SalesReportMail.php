<?php

namespace HiEvents\Mail\Organizer;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Mail\BaseMail;
use HiEvents\Services\Application\Handlers\Event\DTO\EventStatsResponseDTO;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/organizer/sales-report.blade.php
 */
class SalesReportMail extends BaseMail
{
    public function __construct(
        private readonly EventDomainObject    $event,
        private readonly EventStatsResponseDTO $stats,
        private readonly string                $periodLabel,
    )
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Sales Report for :event — :period', [
                'event' => $this->event->getTitle(),
                'period' => $this->periodLabel,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organizer.sales-report',
            with: [
                'event' => $this->event,
                'stats' => $this->stats,
                'periodLabel' => $this->periodLabel,
                'currency' => $this->event->getCurrency(),
            ],
        );
    }
}
