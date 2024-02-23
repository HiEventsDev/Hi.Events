<?php

namespace TicketKitten\Http\Actions\Orders\Payment;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Throwable;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Exceptions\RefundNotPossibleException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\RefundOrderDTO;
use TicketKitten\Http\Request\Order\RefundOrderRequest;
use TicketKitten\Resources\Order\OrderResource;
use TicketKitten\Service\Handler\Order\Payment\Stripe\RefundOrderHandler;

class RefundOrderAction extends BaseAction
{
    public function __construct(private readonly RefundOrderHandler     $refundOrderHandler)
    {
    }

    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function __invoke(RefundOrderRequest $request, int $eventId, int $orderId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $order = $this->refundOrderHandler->handle(
                refundOrderDTO: RefundOrderDTO::fromArray(array_merge($request->validated(), [
                    'event_id' => $eventId,
                    'order_id' => $orderId,
                ]))
            );
        } catch (ApiErrorException|RefundNotPossibleException $exception) {
            throw ValidationException::withMessages([
                'amount' => $exception instanceof ApiErrorException
                    ? 'Stripe error: ' . $exception->getMessage()
                    : $exception->getMessage(),
            ]);
        }

        return $this->resourceResponse(OrderResource::class, $order);
    }
}
