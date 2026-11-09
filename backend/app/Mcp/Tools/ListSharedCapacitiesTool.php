<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\CapacityAssignments\GetCapacityAssignmentsAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_shared_capacities')]
#[Title('List shared capacities')]
#[Description('Lists an event\'s shared capacity limits: caps that several tickets count towards together, with how much is used.')]
#[IsReadOnly]
class ListSharedCapacitiesTool extends ApiTool
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

        $response = $this->api->get(GetCapacityAssignmentsAction::class, ['event_id' => $eventId], ['per_page' => self::PER_PAGE]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'shared_capacities' => collect($response->data())->map(McpPresenter::sharedCapacity(...))->values()->all(),
        ]);
    }
}
