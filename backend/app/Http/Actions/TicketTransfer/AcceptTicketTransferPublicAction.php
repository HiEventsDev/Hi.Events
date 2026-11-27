<?php

namespace HiEvents\Http\Actions\TicketTransfer;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\TicketTransfer\AcceptTicketTransferRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\TicketTransfer\AcceptTicketTransferHandler;
use HiEvents\Services\Application\Handlers\TicketTransfer\DTO\AcceptTicketTransferDTO;
use Illuminate\Http\JsonResponse;
use Throwable;

class AcceptTicketTransferPublicAction extends BaseAction
{
    public function __construct(
        private readonly AcceptTicketTransferHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function __invoke(AcceptTicketTransferRequest $request, string $token): JsonResponse
    {
        try {
            $attendee = $this->handler->handle(new AcceptTicketTransferDTO(
                token: $token,
                first_name: $request->validated('first_name'),
                last_name: $request->validated('last_name'),
                questions: $request->input('questions', []),
                ip_address: $this->getClientIp($request),
                user_agent: $request->userAgent(),
            ));
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->jsonResponse([
            'event_id' => $attendee->getEventId(),
            'attendee_short_id' => $attendee->getShortId(),
        ]);
    }
}
