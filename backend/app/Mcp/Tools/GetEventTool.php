<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\EventOccurrences\GetEventOccurrencesAction;
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

#[Name('get_event')]
#[Title('Get event')]
#[Description('Gets an event with its description, venue, next 50 upcoming dates, tickets and links to its dashboard, public page and preview.')]
#[IsReadOnly]
class GetEventTool extends ApiTool
{
    public const MAX_DATES = 50;

    public const MAX_TICKETS = 100;

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

        $datesResponse = $this->api->get(GetEventOccurrencesAction::class, ['event_id' => $eventId], [
            'per_page' => self::MAX_DATES,
            'filter_fields' => ['start_date' => ['gte' => now()->toDateTimeString()]],
        ]);

        if (! $datesResponse->isSuccessful()) {
            return $this->errorResponse($datesResponse);
        }

        $ticketsResponse = $this->api->get(GetProductsAction::class, ['event_id' => $eventId], [
            'per_page' => self::MAX_TICKETS,
        ]);

        if (! $ticketsResponse->isSuccessful()) {
            return $this->errorResponse($ticketsResponse);
        }

        $event = $eventResponse->data();
        $timezone = $event['timezone'];

        return Response::structured([
            'event' => [
                ...McpPresenter::eventSummary($event),
                'description' => $event['description'] ?? null,
                'venue' => McpPresenter::venue($event['event_location'] ?? null),
            ],
            'upcoming_dates' => collect($datesResponse->data())
                ->map(static fn (array $occurrence): array => McpPresenter::eventDate($occurrence, $timezone))
                ->values()
                ->all(),
            'upcoming_dates_total' => $datesResponse->body['meta']['total'] ?? null,
            'tickets' => collect($ticketsResponse->data())
                ->map(static fn (array $product): array => McpPresenter::ticket($product, $timezone))
                ->values()
                ->all(),
        ]);
    }
}
