<?php

namespace HiEvents\Http\Actions\DocumentTemplates;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\DocumentTemplateRepositoryInterface;
use HiEvents\Resources\DocumentTemplate\DocumentTemplateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetDocumentTemplatesAction extends BaseAction
{
    public function __construct(
        private readonly DocumentTemplateRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();
        $params = QueryParamsDTO::fromArray($request->query());

        $templates = $this->repository->findByAccountId($accountId, $params);

        return $this->resourceResponse(
            resource: DocumentTemplateResource::class,
            data: $templates,
        );
    }
}
