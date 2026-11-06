<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\Http\Actions\Products\CreateProductAction;
use HiEvents\Http\Actions\Products\DeleteProductAction;
use HiEvents\Http\Actions\Products\EditProductAction;
use HiEvents\Http\Actions\Products\GetProductAction;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use HiEvents\Mcp\Tools\Support\TicketPayload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create_addon')]
#[Title('Create ticket add-on')]
#[Description('Adds an add-on to an event and offers it with the given tickets, e.g. parking, a T-shirt or a meal. By default the add-on can only be bought in an order that includes one of those tickets. Types: FREE, PAID (fixed price), DONATION (price is the minimum) or TIERED (several named prices). Prices are decimal amounts in the event currency. If linking a ticket fails, the add-on is deleted again, which also unlinks it from the tickets already done; the error says if that clean-up failed. Counts as one change per ticket plus one.')]
#[IsDestructive]
class CreateAddonTool extends WriteApiTool
{
    public const MAX_PARENT_TICKETS = 20;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'ticket_ids' => $schema->array()->items($schema->integer())->description('The tickets the add-on is offered with.')->required(),
            'title' => $schema->string()->description('Add-on name, e.g. "Parking".')->required(),
            'type' => $schema->string()->enum(TicketPayload::TYPES)->required(),
            'price' => $schema->number()->description('Price for PAID, minimum for DONATION. Not used for FREE or TIERED.'),
            'quantity_available' => $schema->integer()->description('How many can be sold. Leave out for unlimited. Not used for TIERED.'),
            'tiers' => TicketPayload::tiersSchema($schema)->description('Required for TIERED: the prices in the order they are offered.'),
            'description' => $schema->string()->description('Description shown to buyers, as HTML.'),
            'sale_start_date' => $schema->string()->description('When sales open. '.McpPresenter::LOCAL_DATE_DESCRIPTION),
            'sale_end_date' => $schema->string()->description('When sales close. '.McpPresenter::LOCAL_DATE_DESCRIPTION),
            'min_per_order' => $schema->integer()->description('Minimum per order.'),
            'max_per_order' => $schema->integer()->description('Maximum per order.'),
            'is_hidden' => $schema->boolean()->description('Hide from the public event page.'),
            'sold_separately' => $schema->boolean()->description('Also sell the add-on on its own, without one of the tickets. Defaults to false.'),
            'category_id' => $schema->integer()->description('Ticket category to put it in. Defaults to the event\'s first category.'),
            'product_type' => $schema->string()->enum(ProductType::valuesArray())->description('GENERAL (default) for merchandise and extras; TICKET issues an attendee ticket.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'ticket_ids' => 'required|array|min:1|max:'.self::MAX_PARENT_TICKETS,
            'ticket_ids.*' => 'integer|min:1',
            'type' => 'required|in:'.implode(',', TicketPayload::TYPES),
            'tiers' => 'nullable|array',
            'sold_separately' => 'nullable|boolean',
            'category_id' => 'nullable|integer|min:1',
        ]);

        $ticketIds = array_values(array_unique(array_map('intval', $validated['ticket_ids'])));

        if ($limited = $this->writeLimitResponse($request, count($ticketIds) + 1)) {
            return $limited;
        }

        $eventId = (int) $validated['event_id'];
        $eventResponse = $this->getEvent($eventId);

        if (! $eventResponse->isSuccessful()) {
            return $this->errorResponse($eventResponse);
        }

        $timezone = $eventResponse->data()['timezone'];
        $parentPayloads = [];

        foreach ($ticketIds as $ticketId) {
            $parentResponse = $this->api->get(GetProductAction::class, ['event_id' => $eventId, 'ticket_id' => $ticketId]);

            if (! $parentResponse->isSuccessful()) {
                return $this->errorResponse($parentResponse);
            }

            if ($parentResponse->data()['is_addon_only'] ?? false) {
                return Response::error(__('Ticket :id is itself an add-on, so it cannot offer add-ons.', ['id' => $ticketId]));
            }

            $parentPayloads[$ticketId] = TicketPayload::fromExistingProduct($parentResponse->data(), $timezone);
        }

        $categoryId = $this->resolveCategoryId($eventId, $validated['category_id'] ?? null);

        if ($categoryId instanceof Response) {
            return $categoryId;
        }

        $addonResponse = $this->api->send('POST', CreateProductAction::class, ['event_id' => $eventId], [
            ...TicketPayload::forNewProduct($request->all(), $categoryId, ProductType::GENERAL->name),
            'is_addon_only' => ! ($validated['sold_separately'] ?? false),
        ]);

        if (! $addonResponse->isSuccessful()) {
            return $this->errorResponse($addonResponse);
        }

        $addonId = (int) $addonResponse->data()['id'];
        foreach ($parentPayloads as $ticketId => $payload) {
            $linkResponse = $this->api->send('PUT', EditProductAction::class, ['event_id' => $eventId, 'ticket_id' => $ticketId], [
                ...$payload,
                'addon_product_ids' => [...$payload['addon_product_ids'], $addonId],
            ]);

            if (! $linkResponse->isSuccessful()) {
                return $this->failAndRemoveAddon($eventId, $addonId, $linkResponse);
            }

        }

        return Response::structured([
            'addon' => McpPresenter::ticket($addonResponse->data(), $timezone),
            'offered_with_ticket_ids' => $ticketIds,
        ]);
    }

    private function failAndRemoveAddon(int $eventId, int $addonId, ApiRouteResponseDTO $linkResponse): Response
    {
        $deleteResponse = $this->api->send('DELETE', DeleteProductAction::class, ['event_id' => $eventId, 'ticket_id' => $addonId]);

        if ($deleteResponse->isSuccessful()) {
            return $this->errorResponse($linkResponse);
        }

        return Response::error($this->errorMessage($linkResponse)."\n".__(
            'The add-on (id :id) was created but could not be removed. Delete it before trying again.',
            ['id' => $addonId],
        ));
    }
}
