<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\Seating\Resources\EventSeatMapResourcePublic;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\Public\GetEventSeatMapPublicHandler;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GetBoxOfficeSeatMapPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetEventSeatMapPublicHandler $handler,
    ) {}

    public function __invoke(Request $request, string $boxOfficeShortId): Response|JsonResponse
    {
        /** @var BoxOfficeDomainObject $boxOffice */
        $boxOffice = $request->attributes->get(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE);

        try {
            $seatMap = $this->handler->handle($boxOffice->getEventId());
        } catch (ResourceNotFoundException) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(EventSeatMapResourcePublic::class, $seatMap);
    }
}
