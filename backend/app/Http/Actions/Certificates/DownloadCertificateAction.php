<?php

namespace HiEvents\Http\Actions\Certificates;

use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Services\Domain\Certificate\GenerateCertificateService;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DownloadCertificateAction
{
    public function __construct(
        private readonly AttendeeRepositoryInterface      $attendeeRepository,
        private readonly EventRepositoryInterface          $eventRepository,
        private readonly EventSettingsRepositoryInterface   $eventSettingsRepository,
        private readonly GenerateCertificateService         $certificateService,
    )
    {
    }

    public function __invoke(int $eventId, string $attendeeShortId): Response
    {
        $attendee = $this->attendeeRepository->findFirstWhere([
            [AttendeeDomainObjectAbstract::SHORT_ID, '=', $attendeeShortId],
        ]);

        if ($attendee === null) {
            throw new NotFoundHttpException(__('Attendee not found'));
        }

        $event = $this->eventRepository->findById($eventId);
        $settings = $this->eventSettingsRepository->findFirstWhere([
            'event_id' => $eventId,
        ]);

        if (!$settings || !$settings->getCertificateEnabled()) {
            throw new NotFoundHttpException(__('Certificates are not enabled for this event'));
        }

        // Verify attendee has checked in
        if ($attendee->getCheckedInAt() === null) {
            throw new NotFoundHttpException(__('Certificate is only available for checked-in attendees'));
        }

        return $this->certificateService->generate($attendee, $event, $settings);
    }
}
