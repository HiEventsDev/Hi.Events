<?php

namespace HiEvents\Http\Actions\TicketResale;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SelfServiceVerificationRequiredException;
use HiEvents\Resources\TicketResale\TicketResaleStateResourcePublic;
use HiEvents\Services\Application\Handlers\TicketResale\WithdrawTicketResaleHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WithdrawTicketResalePublicAction extends BaseTicketResalePublicAction
{
    public function __construct(
        private readonly WithdrawTicketResaleHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function __invoke(Request $request, int $eventId, string $orderShortId, string $attendeeShortId): JsonResponse
    {
        try {
            return $this->resourceResponse(
                resource: TicketResaleStateResourcePublic::class,
                data: $this->handler->handle($this->link($request, $eventId, $orderShortId, $attendeeShortId)),
            );
        } catch (SelfServiceVerificationRequiredException $exception) {
            return $this->verificationRequired($exception);
        }
    }
}
