<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Widgets\GetEventWidgetsAction;
use HiEvents\Http\Actions\Widgets\GetOrganizerWidgetsAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_widgets')]
#[Title('List embeddable widgets')]
#[Description('Lists the saved widgets of an event or an organizer, with their embed code.')]
#[IsReadOnly]
class ListWidgetsTool extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('List an event\'s widgets.'),
            'organizer_id' => $schema->integer()->description('List an organizer\'s EVENTS widgets.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'nullable|integer|min:1',
            'organizer_id' => 'nullable|integer|min:1',
        ]);

        if (($validated['event_id'] ?? null) === null && ($validated['organizer_id'] ?? null) === null) {
            return Response::error(__('Give an event_id or an organizer_id.'));
        }

        $response = isset($validated['event_id'])
            ? $this->api->get(GetEventWidgetsAction::class, ['event_id' => $validated['event_id']])
            : $this->api->get(GetOrganizerWidgetsAction::class, ['organizer_id' => $validated['organizer_id']]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'widgets' => collect($response->data())->map(McpPresenter::widget(...))->values()->all(),
        ]);
    }
}
