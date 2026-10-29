<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Http\Request\SeatBlockRequest;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\ReleaseSeatBlocksHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ReleaseSeatBlocksAction extends BaseSeatBlockAction
{
    public function __construct(
        private readonly ReleaseSeatBlocksHandler $handler,
    ) {}

    public function __invoke(int $eventId, SeatBlockRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $released = $this->handler->handle($this->seatBlock($eventId, $request));
        } catch (SeatSelectionInvalidException $exception) {
            throw ValidationException::withMessages(['seat_uids' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['released' => $released], wrapInData: true);
    }
}
