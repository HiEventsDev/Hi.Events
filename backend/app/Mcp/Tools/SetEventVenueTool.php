<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\Http\Actions\Events\UpdateEventLocationAction;
use HiEvents\Http\Actions\Locations\CreateLocationAction;
use HiEvents\Http\Actions\Locations\DeleteLocationAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('set_event_venue')]
#[Title('Set event venue')]
#[Description('Sets where an event happens. Give exactly one of: venue_id (a saved venue), address (saves a new venue for the organizer), online_details (an online event), or clear: true (no venue).')]
#[IsDestructive]
class SetEventVenueTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'venue_id' => $schema->integer()->description('A saved venue of the event\'s organizer.'),
            'venue_name' => $schema->string()->description('Name for a new venue, e.g. "The Button Factory".'),
            'address' => $schema->object([
                'address_line_1' => $schema->string(),
                'address_line_2' => $schema->string(),
                'city' => $schema->string(),
                'state_or_region' => $schema->string(),
                'zip_or_postal_code' => $schema->string(),
                'country' => $schema->string()->description('ISO 3166-1 alpha-2 code, e.g. IE.'),
            ])->description('Address of a new venue.'),
            'online_details' => $schema->string()->description('How attendees join an online event, e.g. a meeting link. Shown to ticket holders.'),
            'clear' => $schema->boolean()->description('Remove the event\'s venue.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'venue_id' => 'nullable|integer|min:1',
            'venue_name' => 'nullable|string|max:255',
            'address' => 'nullable|array',
            'online_details' => 'nullable|string',
            'clear' => 'nullable|boolean',
        ]);

        $choices = array_filter([
            isset($validated['venue_id']),
            isset($validated['address']),
            isset($validated['online_details']),
            (bool) ($validated['clear'] ?? false),
        ]);

        if (count($choices) !== 1) {
            return Response::error(__('Give exactly one of venue_id, address, online_details or clear.'));
        }

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $eventId = (int) $validated['event_id'];
        $eventResponse = $this->getEvent($eventId);

        if (! $eventResponse->isSuccessful()) {
            return $this->errorResponse($eventResponse);
        }

        $venueId = $validated['venue_id'] ?? null;
        $createdVenueId = null;

        if (isset($validated['address'])) {
            $locationResponse = $this->api->send('POST', CreateLocationAction::class, [
                'organizer_id' => $eventResponse->data()['organizer_id'],
            ], [
                'name' => $validated['venue_name'] ?? null,
                'structured_address' => [
                    'venue_name' => $validated['venue_name'] ?? null,
                    ...$validated['address'],
                ],
            ]);

            if (! $locationResponse->isSuccessful()) {
                return $this->errorResponse($locationResponse);
            }

            $venueId = $createdVenueId = (int) $locationResponse->data()['id'];
        }

        $payload = match (true) {
            (bool) ($validated['clear'] ?? false) => ['clear_event_location' => true],
            isset($validated['online_details']) => ['event_location' => [
                'type' => LocationType::ONLINE->name,
                'online_event_connection_details' => $validated['online_details'],
            ]],
            default => ['event_location' => [
                'type' => LocationType::IN_PERSON->name,
                'location_id' => $venueId,
            ]],
        };

        $response = $this->api->send('PATCH', UpdateEventLocationAction::class, ['event_id' => $eventId], $payload);

        if (! $response->isSuccessful()) {
            if ($createdVenueId !== null) {
                $this->api->send('DELETE', DeleteLocationAction::class, [
                    'organizer_id' => $eventResponse->data()['organizer_id'],
                    'location_id' => $createdVenueId,
                ]);
            }

            return $this->errorResponse($response);
        }

        return Response::structured([
            'event_id' => $eventId,
            'venue' => McpPresenter::venue($response->data()['event_location'] ?? null),
        ]);
    }
}
