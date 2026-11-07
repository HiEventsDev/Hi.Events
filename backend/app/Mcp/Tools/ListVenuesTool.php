<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Locations\GetLocationsAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_venues')]
#[Title('List venues')]
#[Description('Lists an organizer\'s saved venues, which can be reused across events.')]
#[IsReadOnly]
class ListVenuesTool extends ApiTool
{
    public const PER_PAGE = 100;

    public function schema(JsonSchema $schema): array
    {
        return [
            'organizer_id' => $schema->integer()->description('The organizer id.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $organizerId = (int) $request->validate(['organizer_id' => 'required|integer|min:1'])['organizer_id'];

        $response = $this->api->get(GetLocationsAction::class, ['organizer_id' => $organizerId], [
            'per_page' => self::PER_PAGE,
        ]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'venues' => collect($response->data())->map(McpPresenter::location(...))->values()->all(),
        ]);
    }
}
