<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Lineup\CreateEventSpaceAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('add_lineup_space')]
#[Title('Add stage or room')]
#[Description('Adds a space where schedule items happen, such as a stage, room or track.')]
#[IsDestructive]
class AddLineupSpaceTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'name' => $schema->string()->description('Space name, up to 100 characters, e.g. "Main stage".')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $eventId = (int) $request->validate(['event_id' => 'required|integer|min:1'])['event_id'];

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $response = $this->api->send('POST', CreateEventSpaceAction::class, ['event_id' => $eventId], $request->only(['name']));

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured(['space' => ['id' => $response->data()['id'], 'name' => $response->data()['name']]]);
    }
}
