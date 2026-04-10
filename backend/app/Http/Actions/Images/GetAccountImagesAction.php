<?php

namespace HiEvents\Http\Actions\Images;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Resources\Image\ImageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAccountImagesAction extends BaseAction
{
    public function __construct(private readonly ImageRepositoryInterface $imageRepository)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        $images = $this->imageRepository->findByAccountId(
            $accountId,
            QueryParamsDTO::fromArray($request->query->all()),
        );

        return $this->filterableResourceResponse(
            resource: ImageResource::class,
            data: $images,
            domainObject: ImageDomainObject::class,
        );
    }
}
