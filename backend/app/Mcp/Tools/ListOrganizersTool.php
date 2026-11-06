<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Organizers\GetOrganizersAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_organizers')]
#[Title('List organizers')]
#[Description('Lists the organizers in the connected Hi.Events account. Every event belongs to an organizer, which sets its default timezone and currency.')]
#[IsReadOnly]
class ListOrganizersTool extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $response = $this->api->get(GetOrganizersAction::class);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'organizers' => collect($response->data())
                ->map(McpPresenter::organizer(...))
                ->values()
                ->all(),
        ]);
    }
}
