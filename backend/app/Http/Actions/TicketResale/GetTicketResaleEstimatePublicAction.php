<?php

namespace HiEvents\Http\Actions\TicketResale;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SelfServiceVerificationRequiredException;
use HiEvents\Resources\TicketResale\TicketResaleEstimateResourcePublic;
use HiEvents\Services\Application\Handlers\TicketResale\GetTicketResaleEstimateHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetTicketResaleEstimatePublicAction extends BaseTicketResalePublicAction
{
    public function __construct(
        private readonly GetTicketResaleEstimateHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(Request $request, int $eventId, string $orderShortId, string $attendeeShortId): JsonResponse
    {
        try {
            return $this->resourceResponse(
                resource: TicketResaleEstimateResourcePublic::class,
                data: $this->handler->handle($this->link($request, $eventId, $orderShortId, $attendeeShortId)),
            );
        } catch (SelfServiceVerificationRequiredException $exception) {
            return $this->verificationRequired($exception);
        }
    }
}
