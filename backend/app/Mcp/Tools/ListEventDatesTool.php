<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\EventOccurrences\GetEventOccurrencesAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_event_dates')]
#[Title('List event dates')]
#[Description('Lists an event\'s dates in date order, 50 per page, with capacity and how many places are left. Past dates are left out unless include_past is true.')]
#[IsReadOnly]
class ListEventDatesTool extends ApiTool
{
    public const PER_PAGE = 50;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'include_past' => $schema->boolean()->description('Include dates that have already started.'),
            'page' => $schema->integer()->description('Page number, starting at 1.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'include_past' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
        ]);

        $eventResponse = $this->getEvent((int) $validated['event_id']);

        if (! $eventResponse->isSuccessful()) {
            return $this->errorResponse($eventResponse);
        }

        $response = $this->api->get(GetEventOccurrencesAction::class, ['event_id' => $validated['event_id']], array_filter([
            'per_page' => self::PER_PAGE,
            'page' => $validated['page'] ?? 1,
            'filter_fields' => ($validated['include_past'] ?? false)
                ? null
                : ['start_date' => ['gte' => now()->toDateTimeString()]],
        ]));

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $timezone = $eventResponse->data()['timezone'];

        return Response::structured([
            'dates' => collect($response->data())
                ->map(static fn (array $occurrence): array => McpPresenter::eventDate($occurrence, $timezone))
                ->values()
                ->all(),
            'page' => $response->body['meta']['current_page'] ?? 1,
            'last_page' => $response->body['meta']['last_page'] ?? 1,
            'total' => $response->body['meta']['total'] ?? null,
        ]);
    }
}
