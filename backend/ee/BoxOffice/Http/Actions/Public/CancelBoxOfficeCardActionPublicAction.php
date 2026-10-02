<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\CancelBoxOfficeCardActionPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResourcePublic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CancelBoxOfficeCardActionPublicAction extends BaseAction
{
    public function __construct(
        private readonly CancelBoxOfficeCardActionPublicHandler $handler,
    ) {}

    public function __invoke(Request $request, string $boxOfficeShortId, string $orderShortId): JsonResponse
    {
        /** @var BoxOfficeSessionDTO $session */
        $session = $request->attributes->get(AuthenticateBoxOfficeSession::SESSION_ATTRIBUTE);

        try {
            $order = $this->handler->handle(
                boxOffice: $request->attributes->get(AuthenticateBoxOfficeSession::BOX_OFFICE_ATTRIBUTE),
                orderShortId: $orderShortId,
                readerId: $session->stripe_terminal_reader_id,
            );
        } catch (StripeClientConfigurationException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
        );
    }
}
