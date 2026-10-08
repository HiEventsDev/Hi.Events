<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Public;

use HiEvents\Enterprise\BoxOffice\Http\Middleware\AuthenticateBoxOfficeSession;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\GetBoxOfficeOrderPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResourcePublic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class GetBoxOfficeOrderPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetBoxOfficeOrderPublicHandler $handler,
        private readonly LoggerInterface $logger,
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
        } catch (ResourceConflictException $exception) {
            $this->logger->warning('Box office order could not be synced with Stripe', [
                'order_short_id' => $orderShortId,
                'message' => $exception->getMessage(),
            ]);

            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
        );
    }
}
