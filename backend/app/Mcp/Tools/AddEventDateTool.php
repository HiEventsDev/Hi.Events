<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\EventOccurrences\CreateEventOccurrenceAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('add_event_date')]
#[Title('Add event date')]
#[Description('Adds one date to an event, with an optional capacity and label such as "Evening show".')]
#[IsDestructive]
class AddEventDateTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'start_date' => $schema->string()->description(McpPresenter::LOCAL_DATE_DESCRIPTION)->required(),
            'end_date' => $schema->string()->description('Must be after start_date. '.McpPresenter::LOCAL_DATE_DESCRIPTION),
            'capacity' => $schema->integer()->description('Maximum tickets for this date. Leave out for no date-level limit.'),
            'label' => $schema->string()->description('Short label shown to buyers, up to 255 characters.'),
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

        $response = $this->api->send(
            'POST',
            CreateEventOccurrenceAction::class,
            ['event_id' => $eventId],
            $request->only(['start_date', 'end_date', 'capacity', 'label']),
        );

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'date' => McpPresenter::eventDate($response->data(), $eventResponse->data()['timezone']),
        ]);
    }
}
