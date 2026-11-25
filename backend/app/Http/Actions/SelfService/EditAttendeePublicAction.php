<?php

namespace HiEvents\Http\Actions\SelfService;

use HiEvents\Exceptions\SelfServiceDisabledException;
use HiEvents\Exceptions\SelfServiceVerificationRequiredException;
use HiEvents\Exceptions\TicketListedForResaleException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\SelfService\EditAttendeePublicRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\SelfService\DTO\EditAttendeePublicDTO;
use HiEvents\Services\Application\Handlers\SelfService\EditAttendeePublicHandler;
use HiEvents\Services\Domain\SelfService\SelfServiceVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class EditAttendeePublicAction extends BaseAction
{
    public function __construct(
        private readonly EditAttendeePublicHandler $handler
    ) {}

    public function __invoke(
        EditAttendeePublicRequest $request,
        int $eventId,
        string $orderShortId,
        string $attendeeShortId
    ): JsonResponse {
        try {
            $result = $this->handler->handle(EditAttendeePublicDTO::from([
                'eventId' => $eventId,
                'orderShortId' => $orderShortId,
                'attendeeShortId' => $attendeeShortId,
                'firstName' => $request->input('first_name'),
                'lastName' => $request->input('last_name'),
                'email' => $request->input('email'),
                'ipAddress' => $this->getClientIp($request),
                'userAgent' => $request->userAgent(),
                'sessionToken' => $request->header(SelfServiceVerificationService::SESSION_HEADER),
            ]));

            $response = [
                'message' => __('Attendee updated successfully'),
            ];

            if ($result->shortIdChanged && $result->newShortId) {
                $response['new_short_id'] = $result->newShortId;
            }

            return $this->jsonResponse($response);
        } catch (SelfServiceVerificationRequiredException $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        } catch (TicketListedForResaleException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_CONFLICT);
        } catch (SelfServiceDisabledException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}
