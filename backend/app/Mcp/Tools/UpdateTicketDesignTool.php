<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\Enums\TicketDateDisplayMode;
use HiEvents\Exceptions\RemoteImageException;
use HiEvents\Http\Actions\EventSettings\GetEventSettingsAction;
use HiEvents\Http\Actions\EventSettings\PartialEditEventSettingsAction;
use HiEvents\Mcp\Routing\ApiRouteDispatcher;
use HiEvents\Mcp\Tools\Support\RemoteImageUploader;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update_ticket_design')]
#[Title('Update ticket design')]
#[Description('Changes how an event\'s tickets look in emails and PDFs: accent colour, logo, footer text, layout and how the date is shown. Fields you leave out stay as they are. logo_url must be a public https JPEG, PNG or WebP of at least 100x100.')]
#[IsDestructive]
class UpdateTicketDesignTool extends WriteApiTool
{
    private const DESIGN_FIELDS = ['enabled', 'accent_color', 'footer_text', 'layout_type', 'date_display_mode'];

    private const LAYOUT_TYPES = ['default', 'modern'];

    public function __construct(
        ApiRouteDispatcher $api,
        private readonly RemoteImageUploader $imageUploader,
    ) {
        parent::__construct($api);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'enabled' => $schema->boolean()->description('Use the custom design instead of the default.'),
            'accent_color' => $schema->string()->description('Hex colour, e.g. #6D28D9.'),
            'footer_text' => $schema->string()->description('Text along the bottom of each ticket, up to 500 characters.'),
            'layout_type' => $schema->string()->enum(self::LAYOUT_TYPES),
            'date_display_mode' => $schema->string()->enum(array_column(TicketDateDisplayMode::cases(), 'value'))->description('START_DATE_TIME, DATE_RANGE or HIDDEN.'),
            'logo_url' => $schema->string()->description('Logo shown on the ticket.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'logo_url' => 'nullable|string|max:2048',
        ]);
        $eventId = (int) $validated['event_id'];
        $changes = $request->only(self::DESIGN_FIELDS);

        if ($changes === [] && ($validated['logo_url'] ?? null) === null) {
            return Response::error(__('Give at least one setting to change.'));
        }

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $settingsResponse = $this->api->get(GetEventSettingsAction::class, ['event_id' => $eventId]);

        if (! $settingsResponse->isSuccessful()) {
            return $this->errorResponse($settingsResponse);
        }

        if (($validated['logo_url'] ?? null) !== null) {
            try {
                $logoResponse = $this->imageUploader->upload($validated['logo_url'], ImageType::TICKET_LOGO, $eventId);
            } catch (RemoteImageException $exception) {
                return Response::error($exception->getMessage());
            }

            if (! $logoResponse->isSuccessful()) {
                return $this->errorResponse($logoResponse);
            }

            $changes['logo_image_id'] = $logoResponse->data()['id'];
        }

        $design = [
            ...($settingsResponse->data()['ticket_design_settings'] ?? []),
            ...$changes,
        ];

        if (! in_array($design['layout_type'] ?? null, self::LAYOUT_TYPES, true)) {
            unset($design['layout_type']);
        }

        $response = $this->api->send('PATCH', PartialEditEventSettingsAction::class, ['event_id' => $eventId], [
            'ticket_design_settings' => $design,
        ]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'event_id' => $eventId,
            'ticket_design' => $response->data()['ticket_design_settings'] ?? $design,
        ]);
    }
}
