<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\Exceptions\RemoteImageException;
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

#[Name('set_event_cover_image')]
#[Title('Set event cover image')]
#[Description('Downloads an image from a public https URL and makes it the event\'s cover image, replacing the current one. JPEG, PNG or WebP, up to 5 MB, at least 600 pixels wide; a wide image around 1600x800 works best.')]
#[IsDestructive]
class SetEventCoverImageTool extends WriteApiTool
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
            'image_url' => $schema->string()->description('Public https URL of the image.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'image_url' => 'required|string|max:2048',
        ]);

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        try {
            $response = $this->imageUploader->upload($validated['image_url'], ImageType::EVENT_COVER, (int) $validated['event_id']);
        } catch (RemoteImageException $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $image = $response->data();

        return Response::structured([
            'event_id' => (int) $validated['event_id'],
            'cover_image' => [
                'id' => $image['id'],
                'url' => $image['url'],
                'width' => $image['width'] ?? null,
                'height' => $image['height'] ?? null,
            ],
        ]);
    }
}
