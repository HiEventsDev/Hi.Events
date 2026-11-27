<?php

namespace HiEvents\Http\Actions\TicketResale;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SelfServiceVerificationRequiredException;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\TicketResale\TicketResaleStateResourcePublic;
use HiEvents\Services\Application\Handlers\TicketResale\ListTicketForResaleHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ListTicketForResalePublicAction extends BaseTicketResalePublicAction
{
    public function __construct(
        private readonly ListTicketForResaleHandler $handler,
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
                statusCode: ResponseCodes::HTTP_CREATED,
            );
        } catch (SelfServiceVerificationRequiredException $exception) {
            return $this->verificationRequired($exception);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }
    }
}
