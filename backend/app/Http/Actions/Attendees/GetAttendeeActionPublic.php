<?php

namespace TicketKitten\Http\Actions\Attendees;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use TicketKitten\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\AttendeeRepositoryInterface;
use TicketKitten\Resources\Attendee\AttendeeResourcePublic;

class GetAttendeeActionPublic extends BaseAction
{
    private AttendeeRepositoryInterface $attendeeRepository;

    public function __construct(AttendeeRepositoryInterface $attendeeRepository)
    {
        $this->attendeeRepository = $attendeeRepository;
    }

    /**
     * @todo move to handler
     */
    public function __invoke(int $eventId, string $attendeeShortId): JsonResponse|Response
    {
        $attendee = $this->attendeeRepository->findFirstWhere([
            AttendeeDomainObjectAbstract::SHORT_ID => $attendeeShortId
        ]);

        if (!$attendee) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(AttendeeResourcePublic::class, $attendee);
    }
}
