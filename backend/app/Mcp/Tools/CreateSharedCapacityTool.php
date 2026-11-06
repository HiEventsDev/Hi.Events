<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Status\CapacityAssignmentStatus;
use HiEvents\Http\Actions\CapacityAssignments\CreateCapacityAssignmentAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create_shared_capacity')]
#[Title('Create shared capacity')]
#[Description('Caps the total sold across several tickets, e.g. a 200-person room shared by Standard and VIP tickets. Each ticket keeps its own limit too.')]
#[IsDestructive]
class CreateSharedCapacityTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'name' => $schema->string()->description('Internal name, e.g. "Main hall".')->required(),
            'capacity' => $schema->integer()->description('Total places across the tickets.')->required(),
            'product_ids' => $schema->array()->items($schema->integer())->description('Ticket ids that share the capacity.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $eventId = (int) $request->validate(['event_id' => 'required|integer|min:1'])['event_id'];

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $response = $this->api->send('POST', CreateCapacityAssignmentAction::class, ['event_id' => $eventId], [
            'status' => CapacityAssignmentStatus::ACTIVE->name,
            ...$request->only(['name', 'capacity', 'product_ids']),
        ]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured(['shared_capacity' => McpPresenter::sharedCapacity($response->data())]);
    }
}
