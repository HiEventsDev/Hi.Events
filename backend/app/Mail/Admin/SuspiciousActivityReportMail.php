<?php

namespace HiEvents\Mail\Admin;

use HiEvents\Mail\BaseMail;
use HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityFindingDTO;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/admin/suspicious-activity-report.blade.php
 */
class SuspiciousActivityReportMail extends BaseMail
{
    /**
     * @param  SuspiciousActivityFindingDTO[]  $findings
     */
    public function __construct(
        private readonly array $findings,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('[Action Required] Suspicious activity detected (:count)', [
                'count' => count($this->findings),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin.suspicious-activity-report',
            with: [
                'findings' => $this->findings,
                'adminUrl' => config('app.frontend_url').'/admin',
            ],
        );
    }
}
