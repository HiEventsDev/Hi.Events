<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Resources\BoxOfficeOccupiedSeatResourcePublic;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\GetOccupiedSeatsHandler;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GetBoxOfficeOccupiedSeatsPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetOccupiedSeatsHandler $handler,
        private readonly EventSeatMapLookupService $eventSeatMapLookup,
    ) {}

    public function __invoke(Request $request, string $boxOfficeShortId): Response|JsonResponse
    {
        /** @var BoxOfficeDomainObject $boxOffice */
        $boxOffice = $request->attributes->get(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE);
        /** @var BoxOfficeSessionDTO $session */
        $session = $request->attributes->get(AuthenticateBoxOfficeSession::SESSION_ATTRIBUTE);

        if ($session->event_occurrence_id === null) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(
            BoxOfficeOccupiedSeatResourcePublic::class,
            $this->handler->handle($boxOffice->getEventId(), $session->event_occurrence_id),
            meta: ['version' => $this->eventSeatMapLookup->findForEvent($boxOffice->getEventId())?->getVersion()],
        );
    }
}
