<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\EventSettings\PartialEditEventSettingsAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update_event_settings')]
#[Title('Update event settings')]
#[Description('Changes an event\'s checkout and page settings: support email, messages shown before and after checkout, attendee details, order timeout, website link and search engine settings. Fields you leave out stay as they are. Payment settings are changed in Hi.Events itself.')]
#[IsDestructive]
class UpdateEventSettingsTool extends WriteApiTool
{
    public const SETTINGS = [
        'support_email',
        'pre_checkout_message',
        'post_checkout_message',
        'require_attendee_details',
        'order_timeout_in_minutes',
        'website_url',
        'seo_title',
        'seo_description',
        'allow_search_engine_indexing',
        'notify_organizer_of_new_orders',
    ];

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'support_email' => $schema->string()->description('Email buyers can contact about this event.'),
            'pre_checkout_message' => $schema->string()->description('HTML shown on the checkout page before payment.'),
            'post_checkout_message' => $schema->string()->description('HTML shown after a successful order.'),
            'require_attendee_details' => $schema->boolean()->description('Ask for a name and email for every ticket, not only the buyer.'),
            'order_timeout_in_minutes' => $schema->integer()->description('Minutes a buyer has to finish checkout, 1-120.'),
            'website_url' => $schema->string()->description('The event\'s own website.'),
            'seo_title' => $schema->string()->description('Search engine title, up to 255 characters.'),
            'seo_description' => $schema->string()->description('Search engine description, up to 255 characters.'),
            'allow_search_engine_indexing' => $schema->boolean()->description('Let search engines index the event page.'),
            'notify_organizer_of_new_orders' => $schema->boolean()->description('Email the organizer about each new order.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $eventId = (int) $request->validate(['event_id' => 'required|integer|min:1'])['event_id'];
        $settings = $request->only(self::SETTINGS);

        if ($settings === []) {
            return Response::error(__('Give at least one setting to change.'));
        }

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $response = $this->api->send('PATCH', PartialEditEventSettingsAction::class, ['event_id' => $eventId], $settings);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'event_id' => $eventId,
            'settings' => array_intersect_key($response->data(), array_flip(self::SETTINGS)),
        ]);
    }
}
