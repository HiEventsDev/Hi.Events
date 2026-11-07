<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\ProductCategories\CreateProductCategoryAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create_ticket_category')]
#[Title('Create ticket category')]
#[Description('Adds a category to group tickets on the event page, e.g. "Weekend passes" or "Workshops".')]
#[IsDestructive]
class CreateTicketCategoryTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'name' => $schema->string()->description('Category name, up to 50 characters.')->required(),
            'description' => $schema->string()->description('Text shown above the category\'s tickets.'),
            'is_hidden' => $schema->boolean()->description('Hide the whole category from the event page.'),
            'no_products_message' => $schema->string()->description('Shown when the category has no tickets on sale.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $eventId = (int) $request->validate(['event_id' => 'required|integer|min:1'])['event_id'];

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $response = $this->api->send('POST', CreateProductCategoryAction::class, ['event_id' => $eventId], [
            'is_hidden' => false,
            ...$request->only(['name', 'description', 'is_hidden', 'no_products_message']),
        ]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $category = $response->data();

        return Response::structured([
            'category' => [
                'id' => $category['id'],
                'name' => $category['name'],
                'description' => $category['description'] ?? null,
                'is_hidden' => (bool) ($category['is_hidden'] ?? false),
            ],
        ]);
    }
}
