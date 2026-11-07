<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\WidgetType;
use HiEvents\Http\Actions\Widgets\CreateEventWidgetAction;
use HiEvents\Http\Actions\Widgets\CreateOrganizerWidgetAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create_widget')]
#[Title('Create embeddable widget')]
#[Description('Saves a widget for embedding on another website and returns its HTML embed code. Event widgets: TICKETS (ticket selection and checkout), BUTTON (a button that opens checkout), LINEUP or SCHEDULE. Organizer widgets: EVENTS (a list of the organizer\'s events).')]
#[IsDestructive]
class CreateWidgetTool extends WriteApiTool
{
    private const SETTINGS = [
        'button_text',
        'primary_color',
        'primary_text_color',
        'secondary_color',
        'secondary_text_color',
        'theme',
        'checkout',
        'layout',
        'limit',
        'show_past',
    ];

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('For event widgets.'),
            'organizer_id' => $schema->integer()->description('For EVENTS widgets.'),
            'type' => $schema->string()->enum(array_column(WidgetType::cases(), 'value'))->required(),
            'name' => $schema->string()->description('Internal name, e.g. "Homepage ticket box".')->required(),
            'button_text' => $schema->string()->description('BUTTON: the label, e.g. "Get tickets".'),
            'primary_color' => $schema->string()->description('Hex colour for buttons and highlights.'),
            'primary_text_color' => $schema->string()->description('Hex colour for text on primary_color.'),
            'secondary_color' => $schema->string()->description('BUTTON: hex background colour of the button.'),
            'secondary_text_color' => $schema->string()->description('BUTTON: hex text colour of the button.'),
            'theme' => $schema->string()->enum(['event', 'custom'])->description('event uses the event page colours; custom uses the colours given here.'),
            'checkout' => $schema->string()->enum(['modal', 'new-tab'])->description('Open checkout over the host page or in a new tab.'),
            'layout' => $schema->string()->enum(['list', 'grid', 'compact'])->description('EVENTS: how events are laid out.'),
            'limit' => $schema->integer()->description('EVENTS: how many events to show, 1-50.'),
            'show_past' => $schema->boolean()->description('EVENTS: include past events.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'nullable|integer|min:1',
            'organizer_id' => 'nullable|integer|min:1',
            'type' => 'required|string',
        ]);

        $isOrganizerWidget = $validated['type'] === WidgetType::EVENTS->value;
        $ownerId = $isOrganizerWidget ? ($validated['organizer_id'] ?? null) : ($validated['event_id'] ?? null);

        if ($ownerId === null) {
            return Response::error($isOrganizerWidget
                ? __('An EVENTS widget needs an organizer_id.')
                : __('This widget type needs an event_id.'));
        }

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $response = $this->api->send(
            'POST',
            $isOrganizerWidget ? CreateOrganizerWidgetAction::class : CreateEventWidgetAction::class,
            $isOrganizerWidget ? ['organizer_id' => $ownerId] : ['event_id' => $ownerId],
            [
                'name' => $request->get('name'),
                'type' => $validated['type'],
                'settings' => $request->only(self::SETTINGS) ?: null,
            ],
        );

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured(['widget' => McpPresenter::widget($response->data())]);
    }
}
