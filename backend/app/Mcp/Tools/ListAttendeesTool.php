<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Http\Actions\Attendees\GetAttendeesAction;
use HiEvents\Mcp\Tools\Support\SalesPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_attendees')]
#[Title('List attendees')]
#[Description('Lists an event\'s attendees, 50 per page, newest first: name, email, status, ticket id, date, seat, order and whether they have checked in. Search matches name, email, attendee reference or seat. Filter by status, ticket or date of a recurring event. Registration answers are only returned for a single attendee.')]
#[IsReadOnly]
class ListAttendeesTool extends ApiTool
{
    public const PER_PAGE = 50;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'search' => $schema->string()->description('Name, email, attendee reference or seat to search for.'),
            'status' => $schema->string()->enum(AttendeeStatus::valuesArray())->description('Only attendees with this status.'),
            'ticket_id' => $schema->integer()->description('Only attendees holding this ticket.'),
            'date_id' => $schema->integer()->description('Only attendees for this date of a recurring event.'),
            'page' => $schema->integer()->description('Page number, starting at 1.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'search' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(AttendeeStatus::valuesArray())],
            'ticket_id' => 'nullable|integer|min:1',
            'date_id' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
        ]);

        $eventId = (int) $validated['event_id'];
        $event = $this->getEvent($eventId);

        if (! $event->isSuccessful()) {
            return $this->errorResponse($event);
        }

        $filters = array_filter([
            'status' => $validated['status'] ?? null,
            'product_id' => $validated['ticket_id'] ?? null,
            'event_occurrence_id' => $validated['date_id'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        $response = $this->api->get(GetAttendeesAction::class, ['event_id' => $eventId], array_filter([
            'per_page' => self::PER_PAGE,
            'page' => $validated['page'] ?? 1,
            'query' => $validated['search'] ?? null,
            'filter_fields' => array_map(static fn (mixed $value): array => ['eq' => $value], $filters) ?: null,
        ], static fn (mixed $value): bool => $value !== null));

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $timezone = $event->data()['timezone'];

        return Response::structured([
            'attendees' => collect($response->data())
                ->map(static fn (array $attendee): array => SalesPresenter::attendee($attendee, $timezone))
                ->values()
                ->all(),
            'page' => $response->body['meta']['current_page'] ?? 1,
            'last_page' => $response->body['meta']['last_page'] ?? 1,
            'total' => $response->body['meta']['total'] ?? null,
        ]);
    }
}
