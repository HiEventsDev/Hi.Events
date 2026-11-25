<?php

namespace HiEvents\Http\Actions\TicketTransfer;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\TicketTransfer\DeclineTicketTransferHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class DeclineTicketTransferPublicAction extends BaseAction
{
    public function __construct(
        private readonly DeclineTicketTransferHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function __invoke(Request $request, string $token): JsonResponse|Response
    {
        try {
            $this->handler->handle($token, $this->getClientIp($request), $request->userAgent());
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->noContentResponse();
    }
}
