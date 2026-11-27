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
        $otherFindings = count($this->findings) - 1;
        $summary = $this->findings[0]->summary;

        return new Envelope(
            subject: $otherFindings > 0
                ? __('[Action Required] :summary (+:count more)', ['summary' => $summary, 'count' => $otherFindings])
                : __('[Action Required] :summary', ['summary' => $summary]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin.suspicious-activity-report',
            with: [
                'findings' => $this->findings,
                'adminUrl' => rtrim((string) config('app.frontend_url'), '/').'/admin',
            ],
        );
    }
}
