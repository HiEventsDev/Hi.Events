<?php

namespace HiEvents\Http\Actions\Images;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Exceptions\ImageInUseException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Images\DeleteImageHandler;
use HiEvents\Services\Application\Handlers\Images\DTO\DeleteImageDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeleteImageAction extends BaseAction
{
    public function __construct(
        public readonly DeleteImageHandler $deleteImageHandler,
    ) {}

    /**
     * @throws CannotDeleteEntityException
     */
    public function __invoke(int $imageId, Request $request): JsonResponse|Response
    {
        $this->isActionAuthorized($imageId, ImageDomainObject::class);

        $confirm = $request->boolean('confirm');

        try {
            $this->deleteImageHandler->handle(new DeleteImageDTO(
                imageId: $imageId,
                userId: $this->getAuthenticatedUser()->getId(),
                accountId: $this->getAuthenticatedAccountId(),
                confirm: $confirm,
            ));
        } catch (ImageInUseException $exception) {
            return $this->jsonResponse([
                'message' => $exception->getMessage(),
                'has_protected_references' => $exception->hasProtectedReferences,
                'deletable_with_confirm' => ! $exception->hasProtectedReferences,
                'references' => array_map(static fn ($r) => $r->toArray(), $exception->references),
            ], ResponseCodes::HTTP_CONFLICT);
        }

        return $this->noContentResponse();
    }
}
