<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\Http\Actions\Products\CreateProductAction;
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

#[Name('create_ticket')]
#[Title('Create ticket')]
#[Description('Adds a ticket to an event. Types: FREE, PAID (fixed price), DONATION (price is the minimum) or TIERED (several named prices, e.g. Early bird and Standard). Prices are decimal amounts in the event currency. Selling paid tickets also needs a payment method set up in Hi.Events.')]
#[IsDestructive]
class CreateTicketTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'title' => $schema->string()->description('Ticket name, e.g. "General admission".')->required(),
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
            'category_id' => $schema->integer()->description('Ticket category to put it in. Defaults to the event\'s first category.'),
            'product_type' => $schema->string()->enum(ProductType::valuesArray())->description('TICKET (default) issues attendee tickets; GENERAL is for merchandise and extras.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'type' => 'required|in:'.implode(',', TicketPayload::TYPES),
            'tiers' => 'nullable|array',
            'category_id' => 'nullable|integer|min:1',
        ]);

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $eventId = (int) $validated['event_id'];

        $eventResponse = $this->getEvent($eventId);

        if (! $eventResponse->isSuccessful()) {
            return $this->errorResponse($eventResponse);
        }

        $categoryId = $this->resolveCategoryId($eventId, $validated['category_id'] ?? null);

        if ($categoryId instanceof Response) {
            return $categoryId;
        }

        $payload = TicketPayload::forNewProduct($request->all(), $categoryId, ProductType::TICKET->name);

        $response = $this->api->send('POST', CreateProductAction::class, ['event_id' => $eventId], $payload);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'ticket' => McpPresenter::ticket($response->data(), $eventResponse->data()['timezone']),
        ]);
    }
}
