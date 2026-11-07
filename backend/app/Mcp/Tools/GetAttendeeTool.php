<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Attendees\GetAttendeeAction;
use HiEvents\Mcp\Tools\Support\SalesPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_attendee')]
#[Title('Get attendee')]
#[Description('Gets one attendee of an event: name, email, status, ticket, date, seat, order, check-in time, notes and their answers to the event\'s per-ticket registration questions.')]
#[IsReadOnly]
class GetAttendeeTool extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'attendee_id' => $schema->integer()->description('The attendee id.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'attendee_id' => 'required|integer|min:1',
        ]);

        $eventId = (int) $validated['event_id'];
        $event = $this->getEvent($eventId);

        if (! $event->isSuccessful()) {
            return $this->errorResponse($event);
        }

        $response = $this->api->get(GetAttendeeAction::class, ['event_id' => $eventId, 'attendee_id' => (int) $validated['attendee_id']]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'attendee' => SalesPresenter::attendee($response->data(), $event->data()['timezone']),
        ]);
    }
}
