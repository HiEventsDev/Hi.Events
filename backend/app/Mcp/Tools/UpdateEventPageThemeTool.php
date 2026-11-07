<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\HomepageBackgroundType;
use HiEvents\DomainObjects\Enums\HomepageFontFamily;
use HiEvents\DomainObjects\Enums\HomepageLayout;
use HiEvents\Http\Actions\EventSettings\GetEventSettingsAction;
use HiEvents\Http\Actions\EventSettings\PartialEditEventSettingsAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update_event_page_theme')]
#[Title('Update event page theme')]
#[Description('Changes the look of the public event page: layout, accent and background colours, light or dark mode, and font. Fields you leave out stay as they are.')]
#[IsDestructive]
class UpdateEventPageThemeTool extends WriteApiTool
{
    private const THEME_FIELDS = ['layout', 'accent', 'background', 'mode', 'background_type', 'font_family'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'layout' => $schema->string()->enum(array_column(HomepageLayout::cases(), 'value'))->description('MARQUEE (wide banner), FLYER (poster beside the details), STORY (long-form, centred) or INVITE (card).'),
            'accent' => $schema->string()->description('Hex accent colour for buttons and highlights.'),
            'background' => $schema->string()->description('Hex page background colour.'),
            'mode' => $schema->string()->enum(['light', 'dark']),
            'background_type' => $schema->string()->enum(array_column(HomepageBackgroundType::cases(), 'value'))->description('COLOR uses background; MIRROR_COVER_IMAGE blurs the cover image behind the page.'),
            'font_family' => $schema->string()->enum(array_column(HomepageFontFamily::cases(), 'value')),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $eventId = (int) $request->validate(['event_id' => 'required|integer|min:1'])['event_id'];
        $changes = $request->only(self::THEME_FIELDS);

        if ($changes === []) {
            return Response::error(__('Give at least one setting to change.'));
        }

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $settingsResponse = $this->api->get(GetEventSettingsAction::class, ['event_id' => $eventId]);

        if (! $settingsResponse->isSuccessful()) {
            return $this->errorResponse($settingsResponse);
        }

        $theme = [
            ...($settingsResponse->data()['homepage_theme_settings'] ?? []),
            ...$changes,
        ];

        $response = $this->api->send('PATCH', PartialEditEventSettingsAction::class, ['event_id' => $eventId], array_filter([
            'homepage_theme_settings' => $theme,
            'homepage_primary_color' => $theme['accent'] ?? null,
            'homepage_body_background_color' => $theme['background'] ?? null,
            'homepage_background_type' => $theme['background_type'] ?? null,
        ], static fn (mixed $value): bool => $value !== null));

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'event_id' => $eventId,
            'theme' => $response->data()['homepage_theme_settings'] ?? $theme,
        ]);
    }
}
