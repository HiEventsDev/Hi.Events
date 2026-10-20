<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Http\Request\ResendBoxOfficeOrderConfirmationPublicRequest;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\ResendBoxOfficeOrderConfirmationPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResourcePublic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ResendBoxOfficeOrderConfirmationPublicAction extends BaseAction
{
    public function __construct(
        private readonly ResendBoxOfficeOrderConfirmationPublicHandler $handler,
    ) {}

    public function __invoke(
        ResendBoxOfficeOrderConfirmationPublicRequest $request,
        string $boxOfficeShortId,
        string $orderShortId,
    ): JsonResponse {
        /** @var BoxOfficeSessionDTO $session */
        $session = $request->attributes->get(AuthenticateBoxOfficeSession::SESSION_ATTRIBUTE);

        try {
            $order = $this->handler->handle(
                boxOffice: $request->attributes->get(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE),
                orderShortId: $orderShortId,
                email: $request->validated('email'),
                operatorName: $session->operator_name,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
        );
    }
}
