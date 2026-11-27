<?php

namespace HiEvents\Http\Actions\TicketTransfer;

use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CancelOrderTicketTransferPublicAction extends BaseTicketTransferPublicAction
{
    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(Request $request, int $eventId, string $orderShortId, string $attendeeShortId): JsonResponse|Response
    {
        return $this->cancel($request, $eventId, $attendeeShortId, $orderShortId);
    }
}
