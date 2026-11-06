<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Products\GetProductsAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_tickets')]
#[Title('List tickets')]
#[Description('Lists an event\'s tickets and other products with their prices, quantities, sale dates and visibility.')]
#[IsReadOnly]
class ListTicketsTool extends ApiTool
{
    public const PER_PAGE = 100;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $eventId = (int) $request->validate(['event_id' => 'required|integer|min:1'])['event_id'];

        $eventResponse = $this->getEvent($eventId);

        if (! $eventResponse->isSuccessful()) {
            return $this->errorResponse($eventResponse);
        }

        $response = $this->api->get(GetProductsAction::class, ['event_id' => $eventId], ['per_page' => self::PER_PAGE]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $timezone = $eventResponse->data()['timezone'];

        return Response::structured([
            'tickets' => collect($response->data())
                ->map(static fn (array $product): array => McpPresenter::ticket($product, $timezone))
                ->values()
                ->all(),
        ]);
    }
}
