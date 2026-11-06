<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\Http\Actions\Products\EditProductAction;
use HiEvents\Http\Actions\Products\GetProductAction;
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

#[Name('update_ticket')]
#[Title('Update ticket')]
#[Description('Changes a ticket. Fields you leave out stay as they are. For TIERED tickets, tiers replaces the whole list: include existing tiers by id to keep them; tiers left out are removed unless they have sales. The ticket type cannot be changed.')]
#[IsDestructive]
class UpdateTicketTool extends WriteApiTool
{
    private const PRODUCT_FIELDS = ['title', 'description', 'sale_start_date', 'sale_end_date', 'min_per_order', 'max_per_order', 'is_hidden'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'ticket_id' => $schema->integer()->description('The ticket id.')->required(),
            'title' => $schema->string(),
            'description' => $schema->string()->description('Description shown to buyers, as HTML.'),
            'price' => $schema->number()->description('New price for PAID, new minimum for DONATION.'),
            'quantity_available' => $schema->integer()->nullable()->description('How many can be sold; null for unlimited. Not used for TIERED.'),
            'tiers' => TicketPayload::tiersSchema($schema)->description('TIERED only: the full list of prices. Fields left out of an existing tier keep their current values.'),
            'sale_start_date' => $schema->string()->nullable()->description(McpPresenter::LOCAL_DATE_DESCRIPTION.' Null to remove.'),
            'sale_end_date' => $schema->string()->nullable()->description(McpPresenter::LOCAL_DATE_DESCRIPTION.' Null to remove.'),
            'min_per_order' => $schema->integer(),
            'max_per_order' => $schema->integer(),
            'is_hidden' => $schema->boolean()->description('Hide from the public event page.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'ticket_id' => 'required|integer|min:1',
            'tiers' => 'nullable|array',
        ]);

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $routeParameters = ['event_id' => $validated['event_id'], 'ticket_id' => $validated['ticket_id']];

        $eventResponse = $this->getEvent((int) $validated['event_id']);

        if (! $eventResponse->isSuccessful()) {
            return $this->errorResponse($eventResponse);
        }

        $productResponse = $this->api->get(GetProductAction::class, $routeParameters);

        if (! $productResponse->isSuccessful()) {
            return $this->errorResponse($productResponse);
        }

        $timezone = $eventResponse->data()['timezone'];
        $product = $productResponse->data();
        $isTiered = $product['type'] === ProductPriceType::TIERED->name;

        $payload = [
            ...TicketPayload::fromExistingProduct($product, $timezone),
            ...$request->only(self::PRODUCT_FIELDS),
        ];

        if ($isTiered && isset($validated['tiers'])) {
            $payload['prices'] = TicketPayload::mergeTiers($payload['prices'], $validated['tiers']);
        }

        if (! $isTiered && $request->has('price')) {
            $payload['prices'][0]['price'] = $request->get('price');
        }

        if (! $isTiered && $request->exists('quantity_available')) {
            $payload['prices'][0]['initial_quantity_available'] = $request->get('quantity_available');
        }

        $response = $this->api->send('PUT', EditProductAction::class, $routeParameters, $payload);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured(['ticket' => McpPresenter::ticket($response->data(), $timezone)]);
    }
}
