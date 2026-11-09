<?php

namespace HiEvents\Mcp\Tools\Support;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\Exceptions\RemoteImageException;
use HiEvents\Http\Actions\Images\CreateImageAction;
use HiEvents\Mcp\Routing\ApiRouteDispatcher;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use HiEvents\Services\Infrastructure\Image\RemoteImageFetcher;

class RemoteImageUploader
{
    public function __construct(
        private readonly ApiRouteDispatcher $api,
        private readonly RemoteImageFetcher $fetcher,
    ) {}

    /**
     * @throws RemoteImageException
     */
    public function upload(string $url, ImageType $type, int $entityId): ApiRouteResponseDTO
    {
        $file = $this->fetcher->fetch($url);

        try {
            return $this->api->upload(CreateImageAction::class, [], [
                'image_type' => $type->name,
                'entity_id' => $entityId,
            ], ['image' => $file]);
        } finally {
            @unlink($file->getPathname());
        }
    }
}
