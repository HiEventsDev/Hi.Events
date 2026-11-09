<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\ProductCategories\GetProductCategoriesAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_ticket_categories')]
#[Title('List ticket categories')]
#[Description('Lists the categories that group an event\'s tickets on its page, in display order, with the tickets in each. Every event starts with one category.')]
#[IsReadOnly]
class ListTicketCategoriesTool extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $eventId = (int) $request->validate(['event_id' => 'required|integer|min:1'])['event_id'];

        $response = $this->api->get(GetProductCategoriesAction::class, ['event_id' => $eventId]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'categories' => collect($response->data())
                ->map(static fn (array $category): array => [
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'description' => $category['description'] ?? null,
                    'is_hidden' => (bool) ($category['is_hidden'] ?? false),
                    'tickets' => collect($category['products'] ?? [])
                        ->map(static fn (array $product): array => ['id' => $product['id'], 'title' => $product['title']])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
