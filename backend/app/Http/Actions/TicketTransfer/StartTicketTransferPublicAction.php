<?php

namespace HiEvents\Http\Actions\TicketTransfer;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Request\TicketTransfer\StartTicketTransferRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class StartTicketTransferPublicAction extends BaseTicketTransferPublicAction
{
    /**
     * @throws ResourceNotFoundException
     * @throws ValidationException
     */
    public function __invoke(StartTicketTransferRequest $request, int $eventId, string $attendeeShortId): JsonResponse
    {
        return $this->start($request, $eventId, $attendeeShortId, null);
    }
}
