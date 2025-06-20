<?php

namespace HiEvents\Http\Actions\Images;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Image\CreateImageRequest;
use HiEvents\Resources\Image\ImageResource;
use HiEvents\Services\Application\Handlers\Images\CreateImageHandler;
use HiEvents\Services\Application\Handlers\Images\DTO\CreateImageDTO;
use HiEvents\Services\Infrastructure\Image\Exception\CouldNotUploadImageException;
use Illuminate\Http\JsonResponse;

class CreateImageAction extends BaseAction
{
    public function __construct(
        public readonly CreateImageHandler $createImageHandler,
    )
    {
    }

    /**
     * @throws CouldNotUploadImageException
     */
    public function __invoke(CreateImageRequest $request): JsonResponse
    {
        $image = $this->createImageHandler->handle(new CreateImageDTO(
            userId: $this->getAuthenticatedUser()->getId(),
            accountId: $this->getAuthenticatedAccountId(),
            image: $request->file('image'),
            imageType: $request->has('image_type') ? ImageType::fromName($request->input('image_type')) : null,
            entityId: $request->input('entity_id'),
        ));

        return $this->resourceResponse(ImageResource::class, $image);
    }
}
