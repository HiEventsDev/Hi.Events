<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Lineup\CreateScheduleItemAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('add_schedule_item')]
#[Title('Add schedule item')]
#[Description('Adds a slot to the event schedule, such as a set, talk or break, optionally in a space and with lineup performers. Needs a title or at least one performer.')]
#[IsDestructive]
class AddScheduleItemTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'title' => $schema->string()->description('Slot title, e.g. "Doors open". Optional when performers are given.'),
            'description' => $schema->string()->description('Details as HTML.'),
            'starts_at' => $schema->string()->description(McpPresenter::LOCAL_DATE_DESCRIPTION)->required(),
            'ends_at' => $schema->string()->description('After starts_at. '.McpPresenter::LOCAL_DATE_DESCRIPTION)->required(),
            'event_space_id' => $schema->integer()->description('The stage or room.'),
            'lineup_entry_ids' => $schema->array()->items($schema->integer())->description('Lineup performer ids (not profile ids) in this slot, up to 50.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $eventId = (int) $request->validate(['event_id' => 'required|integer|min:1'])['event_id'];

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $eventResponse = $this->getEvent($eventId);

        if (! $eventResponse->isSuccessful()) {
            return $this->errorResponse($eventResponse);
        }

        $response = $this->api->send('POST', CreateScheduleItemAction::class, ['event_id' => $eventId], $request->only([
            'title', 'description', 'starts_at', 'ends_at', 'event_space_id', 'lineup_entry_ids',
        ]));

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'schedule_item' => McpPresenter::scheduleItem($response->data(), $eventResponse->data()['timezone']),
        ]);
    }
}
