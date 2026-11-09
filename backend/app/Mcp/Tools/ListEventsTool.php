<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Http\Actions\Events\GetEventsAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_events')]
#[Title('List events')]
#[Description('Lists events in the connected account, newest first, 25 per page. Filter by organizer, status, timing or a title search.')]
#[IsReadOnly]
class ListEventsTool extends ApiTool
{
    public const PER_PAGE = 25;

    public function schema(JsonSchema $schema): array
    {
        return [
            'organizer_id' => $schema->integer()->description('Only events of this organizer.'),
            'status' => $schema->string()->enum(EventStatus::valuesArray())->description('Only events with this status.'),
            'timing' => $schema->string()->enum(['upcoming', 'ended'])->description('Only upcoming or only ended events.'),
            'search' => $schema->string()->description('Text to match in the event title.'),
            'page' => $schema->integer()->description('Page number, starting at 1.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'organizer_id' => 'nullable|integer|min:1',
            'status' => 'nullable|string',
            'timing' => 'nullable|in:upcoming,ended',
            'search' => 'nullable|string|max:150',
            'page' => 'nullable|integer|min:1',
        ]);

        $filters = array_filter([
            'organizer_id' => isset($validated['organizer_id']) ? ['eq' => $validated['organizer_id']] : null,
            'status' => isset($validated['status']) ? ['eq' => $validated['status']] : null,
        ]);

        $response = $this->api->get(GetEventsAction::class, [], array_filter([
            'per_page' => self::PER_PAGE,
            'page' => $validated['page'] ?? 1,
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
            'query' => $validated['search'] ?? null,
            'eventsStatus' => $validated['timing'] ?? null,
            'filter_fields' => $filters ?: null,
        ]));

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'events' => collect($response->data())->map(McpPresenter::eventSummary(...))->values()->all(),
            'page' => $response->body['meta']['current_page'] ?? 1,
            'last_page' => $response->body['meta']['last_page'] ?? 1,
            'total' => $response->body['meta']['total'] ?? null,
        ]);
    }
}
