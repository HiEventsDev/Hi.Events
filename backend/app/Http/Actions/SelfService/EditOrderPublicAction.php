<?php

namespace HiEvents\Http\Actions\SelfService;

use HiEvents\Exceptions\SelfServiceDisabledException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\SelfService\EditOrderPublicRequest;
use HiEvents\Services\Application\Handlers\SelfService\DTO\EditOrderPublicDTO;
use HiEvents\Services\Application\Handlers\SelfService\EditOrderPublicHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class EditOrderPublicAction extends BaseAction
{
    public function __construct(
        private readonly EditOrderPublicHandler $handler
    ) {
    }

    public function __invoke(
        EditOrderPublicRequest $request,
        int $eventId,
        string $orderShortId
    ): JsonResponse {
        try {
            $result = $this->handler->handle(EditOrderPublicDTO::from([
                'eventId' => $eventId,
                'orderShortId' => $orderShortId,
                'firstName' => $request->input('first_name'),
                'lastName' => $request->input('last_name'),
                'email' => $request->input('email'),
                'ipAddress' => $this->getClientIp($request),
                'userAgent' => $request->userAgent(),
            ]));

            $response = [
                'message' => __('Order updated successfully'),
            ];

            if ($result->shortIdChanged && $result->newShortId) {
                $response['new_short_id'] = $result->newShortId;
            }

            return $this->jsonResponse($response);
        } catch (SelfServiceDisabledException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}
