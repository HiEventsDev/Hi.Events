<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\Enums\LineupBillingTier;
use HiEvents\Exceptions\RemoteImageException;
use HiEvents\Http\Actions\Lineup\CreateLineupEntryAction;
use HiEvents\Http\Actions\Lineup\CreateProfileAction;
use HiEvents\Http\Actions\Lineup\DeleteProfileAction;
use HiEvents\Mcp\Routing\ApiRouteDispatcher;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use HiEvents\Mcp\Tools\Support\RemoteImageUploader;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('add_lineup_performer')]
#[Title('Add performer to lineup')]
#[Description('Adds a performer or speaker to an event\'s lineup. Give profile_id to reuse a saved profile, or a name (with optional tagline, bio, links and image_url) to save a new profile for the organizer; not both. image_url must be a public https JPEG, PNG or WebP of at least 200x200. If any step fails, no new profile is kept.')]
#[IsDestructive]
class AddLineupPerformerTool extends WriteApiTool
{
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
            'profile_id' => $schema->integer()->description('An existing profile of the event\'s organizer.'),
            'name' => $schema->string()->description('Name for a new profile.'),
            'tagline' => $schema->string()->description('Short line under the name, e.g. "DJ, Berlin".'),
            'bio' => $schema->string()->description('Biography as HTML.'),
            'links' => $schema->array()->items($schema->string())->description('Up to 12 https links, e.g. website and social profiles.'),
            'image_url' => $schema->string()->description('Photo for a new profile.'),
            'billing_tier' => $schema->string()->enum(array_column(LineupBillingTier::cases(), 'value'))->description('HEADLINER, FEATURED or STANDARD (default).'),
            'role_label' => $schema->string()->description('Role at this event, e.g. "Keynote" or "Support act".'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'profile_id' => 'nullable|integer|min:1',
            'name' => 'nullable|string|max:255',
            'image_url' => 'nullable|string|max:2048',
        ]);

        $reusesProfile = ($validated['profile_id'] ?? null) !== null;
        $hasName = ($validated['name'] ?? null) !== null;
        $hasImage = ($validated['image_url'] ?? null) !== null;

        if ($reusesProfile ? ($hasName || $hasImage) : ! $hasName) {
            return Response::error(__('Give a profile_id or a name.'));
        }

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $eventId = (int) $validated['event_id'];

        if ($reusesProfile) {
            $response = $this->createLineupEntry($request, $eventId, (int) $validated['profile_id']);

            return $response->isSuccessful()
                ? Response::structured(['performer' => McpPresenter::lineupEntry($response->data())])
                : $this->errorResponse($response);
        }

        $eventResponse = $this->getEvent($eventId);

        if (! $eventResponse->isSuccessful()) {
            return $this->errorResponse($eventResponse);
        }

        $organizerId = (int) $eventResponse->data()['organizer_id'];
        $profileResponse = $this->api->send('POST', CreateProfileAction::class, [
            'organizer_id' => $organizerId,
        ], $request->only(['name', 'tagline', 'bio', 'links']));

        if (! $profileResponse->isSuccessful()) {
            return $this->errorResponse($profileResponse);
        }

        $profileId = (int) $profileResponse->data()['id'];

        if ($hasImage) {
            try {
                $imageResponse = $this->imageUploader->upload($validated['image_url'], ImageType::PROFILE_IMAGE, $profileId);
            } catch (RemoteImageException $exception) {
                $this->discardProfile($organizerId, $profileId);

                return Response::error($exception->getMessage());
            }

            if (! $imageResponse->isSuccessful()) {
                $this->discardProfile($organizerId, $profileId);

                return $this->errorResponse($imageResponse);
            }
        }

        $response = $this->createLineupEntry($request, $eventId, $profileId);

        if (! $response->isSuccessful()) {
            $this->discardProfile($organizerId, $profileId);

            return $this->errorResponse($response);
        }

        return Response::structured(['performer' => McpPresenter::lineupEntry($response->data())]);
    }

    private function createLineupEntry(Request $request, int $eventId, int $profileId): ApiRouteResponseDTO
    {
        return $this->api->send('POST', CreateLineupEntryAction::class, ['event_id' => $eventId], [
            'profile_id' => $profileId,
            'billing_tier' => $request->get('billing_tier', LineupBillingTier::STANDARD->value),
            'role_label' => $request->get('role_label'),
        ]);
    }

    private function discardProfile(int $organizerId, int $profileId): void
    {
        $this->api->send('DELETE', DeleteProfileAction::class, ['organizer_id' => $organizerId, 'profile_id' => $profileId]);
    }
}
