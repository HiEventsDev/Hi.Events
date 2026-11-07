<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\AttendeeCheckInActionType;
use HiEvents\Http\Actions\Attendees\GetAttendeeAction;
use HiEvents\Http\Actions\CheckInLists\GetCheckInListsAction;
use HiEvents\Http\Actions\CheckInLists\Public\CreateAttendeeCheckInPublicAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('check_in_attendee')]
#[Title('Check in attendee')]
#[Description('Checks an attendee in on one of the event\'s check-in lists, as door staff would. Uses the given list, or the active list that covers the attendee\'s ticket and date. Cancelled attendees cannot be checked in, nor can orders awaiting payment unless the event allows it. Checking in again on the same list changes nothing; another list records a separate check-in.')]
#[IsDestructive]
class CheckInAttendeeTool extends WriteApiTool
{
    private const CHECK_IN_LISTS_PER_PAGE = 100;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'attendee_id' => $schema->integer()->description('The attendee id.')->required(),
            'check_in_list_id' => $schema->integer()->description('The check-in list to use. Defaults to the active list that covers the attendee.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'attendee_id' => 'required|integer|min:1',
            'check_in_list_id' => 'nullable|integer|min:1',
        ]);

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $eventId = (int) $validated['event_id'];
        $event = $this->getEvent($eventId);

        if (! $event->isSuccessful()) {
            return $this->errorResponse($event);
        }

        $attendeeResponse = $this->api->get(GetAttendeeAction::class, ['event_id' => $eventId, 'attendee_id' => (int) $validated['attendee_id']]);

        if (! $attendeeResponse->isSuccessful()) {
            return $this->errorResponse($attendeeResponse);
        }

        $listsResponse = $this->api->get(GetCheckInListsAction::class, ['event_id' => $eventId], ['per_page' => self::CHECK_IN_LISTS_PER_PAGE]);

        if (! $listsResponse->isSuccessful()) {
            return $this->errorResponse($listsResponse);
        }

        $attendee = $attendeeResponse->data();
        $list = $this->chooseList($listsResponse->data(), $attendee, $validated['check_in_list_id'] ?? null);

        if ($list === null) {
            return Response::error(isset($validated['check_in_list_id'])
                ? __('That check-in list does not belong to this event.')
                : __('No active check-in list covers this attendee\'s ticket and date.'));
        }

        $response = $this->api->send('POST', CreateAttendeeCheckInPublicAction::class, ['check_in_list_short_id' => $list['short_id']], [
            'attendees' => [['public_id' => $attendee['public_id'], 'action' => AttendeeCheckInActionType::CHECK_IN->value]],
        ]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $checkIn = $response->data()[0] ?? null;
        $error = $response->body['errors'][$attendee['public_id']] ?? null;

        if ($checkIn === null) {
            return Response::error((string) ($error ?? __('The attendee could not be checked in.')));
        }

        return Response::structured([
            'attendee_id' => $attendee['id'],
            'check_in_list' => ['id' => $list['id'], 'name' => $list['name']],
            'checked_in_at' => McpPresenter::localDate($checkIn['checked_in_at'] ?? null, $event->data()['timezone']),
            'already_checked_in' => $error !== null,
        ]);
    }

    private function chooseList(array $lists, array $attendee, ?int $listId): ?array
    {
        $lists = collect($lists);

        if ($listId !== null) {
            return $lists->firstWhere('id', $listId);
        }

        return $lists
            ->filter(static fn (array $list): bool => ($list['is_active'] ?? true) && ! ($list['is_expired'] ?? false))
            ->filter(static fn (array $list): bool => empty($list['products']) || collect($list['products'])->contains('id', $attendee['product_id']))
            ->filter(static fn (array $list): bool => $list['event_occurrence_id'] === null || $list['event_occurrence_id'] === ($attendee['event_occurrence_id'] ?? null))
            ->sortByDesc(static fn (array $list): int => ($list['event_occurrence_id'] !== null ? 2 : 0) + (($list['is_system_default'] ?? false) ? 1 : 0))
            ->first();
    }
}
