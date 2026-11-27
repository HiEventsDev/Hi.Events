<?php

namespace HiEvents\Http\Actions\TicketTransfer;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SelfServiceVerificationRequiredException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Actions\SelfService\RespondsWhenVerificationIsRequired;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\TicketTransfer\TicketTransferResourcePublic;
use HiEvents\Services\Application\Handlers\TicketTransfer\CancelTicketTransferHandler;
use HiEvents\Services\Application\Handlers\TicketTransfer\DTO\TicketTransferLinkDTO;
use HiEvents\Services\Application\Handlers\TicketTransfer\StartTicketTransferHandler;
use HiEvents\Services\Domain\SelfService\SelfServiceVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

abstract class BaseTicketTransferPublicAction extends BaseAction
{
    use RespondsWhenVerificationIsRequired;

    public const VERIFICATION_REQUIRED = 'VERIFICATION_REQUIRED';

    public function __construct(
        private readonly StartTicketTransferHandler $startHandler,
        private readonly CancelTicketTransferHandler $cancelHandler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ValidationException
     */
    protected function start(Request $request, int $eventId, string $attendeeShortId, ?string $orderShortId): JsonResponse
    {
        try {
            return $this->resourceResponse(
                resource: TicketTransferResourcePublic::class,
                data: $this->startHandler->handle($this->link($request, $eventId, $attendeeShortId, $orderShortId)),
                statusCode: ResponseCodes::HTTP_CREATED,
            );
        } catch (SelfServiceVerificationRequiredException $exception) {
            return $this->verificationRequired($exception);
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['recipient_email' => $exception->getMessage()]);
        }
    }

    /**
     * @throws ResourceNotFoundException
     */
    protected function cancel(Request $request, int $eventId, string $attendeeShortId, ?string $orderShortId): JsonResponse|Response
    {
        try {
            $this->cancelHandler->handle($this->link($request, $eventId, $attendeeShortId, $orderShortId));

            return $this->deletedResponse();
        } catch (SelfServiceVerificationRequiredException $exception) {
            return $this->verificationRequired($exception);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }
    }

    private function link(Request $request, int $eventId, string $attendeeShortId, ?string $orderShortId): TicketTransferLinkDTO
    {
        return new TicketTransferLinkDTO(
            event_id: $eventId,
            attendee_short_id: $attendeeShortId,
            order_short_id: $orderShortId,
            session_token: $request->header(SelfServiceVerificationService::SESSION_HEADER),
            ip_address: $this->getClientIp($request),
            user_agent: $request->userAgent(),
            recipient_email: $request->input('recipient_email'),
        );
    }
}
