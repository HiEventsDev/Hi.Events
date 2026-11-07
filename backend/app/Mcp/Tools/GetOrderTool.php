<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Orders\GetOrderAction;
use HiEvents\Mcp\Tools\Support\SalesPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_order')]
#[Title('Get order')]
#[Description('Gets one order of an event: buyer, status, payment and refund status, items, attendees, promo code, notes and every registration answer given with it (per order and per attendee). Amounts are left out for team members who may not view financials.')]
#[IsReadOnly]
class GetOrderTool extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'order_id' => $schema->integer()->description('The order id.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'order_id' => 'required|integer|min:1',
        ]);

        $eventId = (int) $validated['event_id'];
        $event = $this->getEvent($eventId);

        if (! $event->isSuccessful()) {
            return $this->errorResponse($event);
        }

        $response = $this->api->get(GetOrderAction::class, ['event_id' => $eventId, 'order_id' => (int) $validated['order_id']]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'order' => SalesPresenter::order($response->data(), $event->data()['timezone']),
        ]);
    }
}
