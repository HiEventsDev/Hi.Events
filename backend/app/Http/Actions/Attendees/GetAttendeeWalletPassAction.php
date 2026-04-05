<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Wallet\AppleWalletPassService;
use HiEvents\Services\Domain\Wallet\GoogleWalletPassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GetAttendeeWalletPassAction extends BaseAction
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly EventRepositoryInterface    $eventRepository,
        private readonly AppleWalletPassService      $appleWalletService,
        private readonly GoogleWalletPassService     $googleWalletService,
    )
    {
    }

    public function __invoke(Request $request, int $eventId, int $attendeeId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $attendee = $this->attendeeRepository->findById($attendeeId);

        $event = $this->eventRepository
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->loadRelation(new Relationship(OrganizerDomainObject::class))
            ->findById($eventId);

        /** @var EventSettingDomainObject $eventSettings */
        $eventSettings = $event->getEventSettings();
        /** @var OrganizerDomainObject $organizer */
        $organizer = $event->getOrganizer();

        $platform = $request->query('platform', 'apple');

        if ($platform === 'google') {
            $pass = $this->googleWalletService->generatePass(
                $attendee, $event, $eventSettings, $organizer
            );
            return $this->jsonResponse($pass);
        }

        // Default: Apple Wallet
        $pass = $this->appleWalletService->generatePass(
            $attendee, $event, $eventSettings, $organizer
        );

        return response($pass['content'], 200, [
            'Content-Type' => $pass['mime'],
            'Content-Disposition' => sprintf('attachment; filename="%s"', $pass['filename']),
        ]);
    }
}
