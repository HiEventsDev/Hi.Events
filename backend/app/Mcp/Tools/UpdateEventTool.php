<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\EventCategory;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\Http\Actions\Events\UpdateEventAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update_event')]
#[Title('Update event')]
#[Description('Changes an event\'s title, description, category, timezone or currency, and the date of a SINGLE event. Fields you leave out stay as they are. On a SINGLE event, changing the timezone keeps the local clock time: a 19:00 start stays 19:00, now in the new timezone.')]
#[IsDestructive]
class UpdateEventTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'title' => $schema->string()->description('Event title, up to 150 characters.'),
            'description' => $schema->string()->description('Event description as HTML (p, h3, ul, li, strong, em, a).'),
            'category' => $schema->string()->enum(EventCategory::valuesArray())->description('Event category.'),
            'timezone' => $schema->string()->description('IANA timezone. On a SINGLE event the local start and end times stay the same.'),
            'currency' => $schema->string()->description('ISO 4217 currency code. Cannot change once the event has paid orders.'),
            'start_date' => $schema->string()->description('SINGLE events only. '.McpPresenter::LOCAL_DATE_DESCRIPTION),
            'end_date' => $schema->string()->description('SINGLE events only. '.McpPresenter::LOCAL_DATE_DESCRIPTION),
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

        $event = $eventResponse->data();
        $isRecurring = $event['type'] === EventType::RECURRING->name;

        if ($isRecurring && ($request->get('start_date') !== null || $request->get('end_date') !== null)) {
            return Response::error(__('A recurring event takes its dates from its schedule, so start_date and end_date cannot be set on it.'));
        }

        $payload = [
            'title' => $request->get('title', $event['title']),
            'type' => $event['type'],
            ...$request->only(['description', 'category', 'timezone', 'currency']),
        ];

        if (! $isRecurring) {
            $payload['start_date'] = $request->get('start_date') ?? McpPresenter::localInputDate($event['start_date'] ?? null, $event['timezone']);
            $payload['end_date'] = $request->get('end_date') ?? McpPresenter::localInputDate($event['end_date'] ?? null, $event['timezone']);
        }

        $response = $this->api->send('PUT', UpdateEventAction::class, ['event_id' => $eventId], $payload);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured(['event' => McpPresenter::eventSummary($response->data())]);
    }
}
