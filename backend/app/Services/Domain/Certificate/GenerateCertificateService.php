<?php

namespace HiEvents\Services\Domain\Certificate;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use Illuminate\Http\Response;

class GenerateCertificateService
{
    public function generate(
        AttendeeDomainObject   $attendee,
        EventDomainObject      $event,
        EventSettingDomainObject $settings,
    ): Response
    {
        $title = $settings->getCertificateTitle() ?: __('Certificate of Attendance');

        $bodyTemplate = $settings->getCertificateBodyTemplate();
        $bodyText = $bodyTemplate
            ? $this->renderTemplate($bodyTemplate, $attendee, $event)
            : __('has successfully attended the event listed below.');

        $eventDate = $event->getStartDate()
            ? Carbon::parse($event->getStartDate())->format('F j, Y')
            : '';

        if ($event->getEndDate()) {
            $eventDate .= ' — ' . Carbon::parse($event->getEndDate())->format('F j, Y');
        }

        $certificateId = strtoupper(substr(md5(
            $attendee->getId() . '-' . $event->getId() . '-' . $attendee->getPublicId()
        ), 0, 12));

        $pdf = Pdf::loadView('certificates.attendance', [
            'title' => $title,
            'attendeeName' => $attendee->getFirstName() . ' ' . $attendee->getLastName(),
            'bodyText' => e($bodyText),
            'eventTitle' => $event->getTitle(),
            'eventDate' => $eventDate,
            'signatoryName' => $settings->getCertificateSignatoryName(),
            'signatoryTitle' => $settings->getCertificateSignatoryTitle(),
            'certificateId' => $certificateId,
        ]);

        $pdf->setPaper('a4', 'landscape');

        $filename = sprintf(
            'certificate-%s-%s.pdf',
            str_replace(' ', '-', strtolower($event->getTitle())),
            $attendee->getShortId(),
        );

        return $pdf->download($filename);
    }

    private function renderTemplate(
        string                 $template,
        AttendeeDomainObject   $attendee,
        EventDomainObject      $event,
    ): string
    {
        $replacements = [
            '{{ attendee_name }}' => $attendee->getFirstName() . ' ' . $attendee->getLastName(),
            '{{ attendee_first_name }}' => $attendee->getFirstName(),
            '{{ attendee_last_name }}' => $attendee->getLastName(),
            '{{ event_title }}' => $event->getTitle(),
            '{{ event_date }}' => $event->getStartDate()
                ? Carbon::parse($event->getStartDate())->format('F j, Y')
                : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
