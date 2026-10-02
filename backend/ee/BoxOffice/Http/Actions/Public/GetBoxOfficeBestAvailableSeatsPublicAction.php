<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Http\Request\BoxOfficeBestAvailableSeatsPublicRequest;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeBestAvailableSeatsDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeSeatRequestItemDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\GetBoxOfficeBestAvailableSeatsPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class GetBoxOfficeBestAvailableSeatsPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetBoxOfficeBestAvailableSeatsPublicHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(BoxOfficeBestAvailableSeatsPublicRequest $request, string $boxOfficeShortId): Response|JsonResponse
    {
        /** @var BoxOfficeDomainObject $boxOffice */
        $boxOffice = $request->attributes->get(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE);
        /** @var BoxOfficeSessionDTO $session */
        $session = $request->attributes->get(AuthenticateBoxOfficeSession::SESSION_ATTRIBUTE);

        if ($session->event_occurrence_id === null) {
            return $this->notFoundResponse();
        }

        try {
            $seatUids = $this->handler->handle(new BoxOfficeBestAvailableSeatsDTO(
                event_id: $boxOffice->getEventId(),
                event_occurrence_id: $session->event_occurrence_id,
                items: array_map(fn (array $item) => new BoxOfficeSeatRequestItemDTO(
                    product_id: (int) $item['product_id'],
                    quantity: (int) $item['quantity'],
                ), $request->validated('items')),
                excluded_seat_uids: $request->validated('exclude') ?? [],
            ));
        } catch (ResourceNotFoundException) {
            return $this->notFoundResponse();
        } catch (SeatSelectionInvalidException $exception) {
            throw ValidationException::withMessages(['items' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['seat_uids' => $seatUids], wrapInData: true);
    }
}
