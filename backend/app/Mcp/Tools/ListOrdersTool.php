<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\Actions\Orders\GetOrdersAction;
use HiEvents\Http\Actions\Organizers\GetOrganizerAction;
use HiEvents\Http\Actions\Organizers\Orders\GetOrganizerOrdersAction;
use HiEvents\Mcp\Tools\Support\SalesPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_orders')]
#[Title('List orders')]
#[Description('Lists orders for one event, or across all of an organizer\'s events, 50 per page, newest first: buyer, status, payment and refund status, total, items and when it was placed. Search matches buyer name, email or order reference. Filter by status, e.g. AWAITING_OFFLINE_PAYMENT for unpaid bank-transfer orders. Attendees and registration answers are only returned for a single order. Amounts are left out for team members who may not view financials.')]
#[IsReadOnly]
class ListOrdersTool extends ApiTool
{
    public const PER_PAGE = 50;

    private const LISTED_STATUSES = [OrderStatus::COMPLETED->name, OrderStatus::AWAITING_OFFLINE_PAYMENT->name, OrderStatus::CANCELLED->name];

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('List this event\'s orders.'),
            'organizer_id' => $schema->integer()->description('List orders across all of this organizer\'s events.'),
            'search' => $schema->string()->description('Buyer name, email or order reference to search for.'),
            'status' => $schema->string()->enum(self::LISTED_STATUSES)->description('Only orders with this status.'),
            'page' => $schema->integer()->description('Page number, starting at 1.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'nullable|integer|min:1',
            'organizer_id' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(self::LISTED_STATUSES)],
            'page' => 'nullable|integer|min:1',
        ]);

        $eventId = isset($validated['event_id']) ? (int) $validated['event_id'] : null;
        $organizerId = isset($validated['organizer_id']) ? (int) $validated['organizer_id'] : null;

        if (($eventId === null) === ($organizerId === null)) {
            return Response::error(__('Give an event_id or an organizer_id.'));
        }

        $parent = $eventId !== null
            ? $this->getEvent($eventId)
            : $this->api->get(GetOrganizerAction::class, ['organizer_id' => $organizerId]);

        if (! $parent->isSuccessful()) {
            return $this->errorResponse($parent);
        }

        $query = array_filter([
            'per_page' => self::PER_PAGE,
            'page' => $validated['page'] ?? 1,
            'query' => $validated['search'] ?? null,
            'filter_fields' => isset($validated['status']) ? ['status' => ['eq' => $validated['status']]] : null,
        ], static fn (mixed $value): bool => $value !== null);

        $response = $eventId !== null
            ? $this->api->get(GetOrdersAction::class, ['event_id' => $eventId], $query)
            : $this->api->get(GetOrganizerOrdersAction::class, ['organizer_id' => $organizerId], $query);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $timezone = $parent->data()['timezone'];

        return Response::structured([
            'orders' => collect($response->data())
                ->map(static fn (array $order): array => SalesPresenter::order(Arr::except($order, ['attendees', 'question_answers']), $timezone))
                ->values()
                ->all(),
            'page' => $response->body['meta']['current_page'] ?? 1,
            'last_page' => $response->body['meta']['last_page'] ?? 1,
            'total' => $response->body['meta']['total'] ?? null,
        ]);
    }
}
