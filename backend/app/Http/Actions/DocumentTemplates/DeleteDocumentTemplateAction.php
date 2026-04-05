<?php

namespace HiEvents\Http\Actions\DocumentTemplates;

use HiEvents\DomainObjects\Generated\DocumentTemplateDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\DocumentTemplateRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeleteDocumentTemplateAction extends BaseAction
{
    public function __construct(
        private readonly DocumentTemplateRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(Request $request, int $templateId): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        $this->repository->deleteWhere([
            [DocumentTemplateDomainObjectAbstract::ID, '=', $templateId],
            [DocumentTemplateDomainObjectAbstract::ACCOUNT_ID, '=', $accountId],
        ]);

        return $this->deletedResponse();
    }
}
