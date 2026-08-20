<?php

namespace HiEvents\Http\Actions\Orders\Public;

use Dedoc\Scramble\Attributes\QueryParameter;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResourcePublic;
use HiEvents\Services\Application\Handlers\Order\DTO\GetOrderPublicDTO;
use HiEvents\Services\Application\Handlers\Order\GetOrderPublicHandler;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetOrderActionPublic extends BaseAction
{
    public function __construct(
        private readonly GetOrderPublicHandler $getOrderPublicHandler,
        private readonly CheckoutSessionManagementService $sessionService,
    ) {}

    #[QueryParameter('session_identifier', description: 'Checkout session identifier issued when the order was created. Sets the checkout session cookie on the response.', type: 'string')]
    public function __invoke(int $eventId, string $orderShortId, Request $request): JsonResponse
    {
        $order = $this->getOrderPublicHandler->handle(new GetOrderPublicDTO(
            eventId: $eventId,
            orderShortId: $orderShortId,
            includeEventInResponse: $this->isIncludeRequested($request, 'event'),
        ));

        $response = $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
        );

        if ($request->query->has('session_identifier')) {
            $response->headers->setCookie(
                $this->sessionService->getSessionCookie()
            );
        }

        return $response;
    }
}
