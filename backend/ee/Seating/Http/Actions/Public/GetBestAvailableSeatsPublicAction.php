<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions\Public;

use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\BestAvailableSeatsRequestDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\Public\GetBestAvailableSeatsPublicHandler;
use HiEvents\Http\Actions\Events\BasePublicEventAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class GetBestAvailableSeatsPublicAction extends BasePublicEventAction
{
    public function __construct(
        private readonly GetBestAvailableSeatsPublicHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $occurrenceId, Request $request): Response|JsonResponse
    {
        if (! $this->canUserViewEventWithId($eventId)) {
            return $this->notFoundResponse();
        }

        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'accessible' => ['boolean'],
            'exclude' => ['array', 'max:50'],
            'exclude.*' => ['string', 'max:24'],
        ]);

        try {
            $seatUids = $this->handler->handle(new BestAvailableSeatsRequestDTO(
                event_id: $eventId,
                event_occurrence_id: $occurrenceId,
                product_id: (int) $validated['product_id'],
                quantity: (int) $validated['quantity'],
                accessible: (bool) ($validated['accessible'] ?? false),
                excluded_seat_uids: $validated['exclude'] ?? [],
            ));
        } catch (SeatSelectionInvalidException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['seat_uids' => $seatUids], wrapInData: true);
    }
}
