<?php

namespace HiEvents\Http\Actions\DocumentTemplates;

use HiEvents\DomainObjects\Generated\DocumentTemplateDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\DocumentTemplateRepositoryInterface;
use HiEvents\Resources\DocumentTemplate\DocumentTemplateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetDocumentTemplateAction extends BaseAction
{
    public function __construct(
        private readonly DocumentTemplateRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(Request $request, int $templateId): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        $template = $this->repository->findFirstWhere([
            [DocumentTemplateDomainObjectAbstract::ID, '=', $templateId],
            [DocumentTemplateDomainObjectAbstract::ACCOUNT_ID, '=', $accountId],
        ]);

        if ($template === null) {
            throw new NotFoundHttpException(__('Document template not found'));
        }

        return $this->resourceResponse(
            resource: DocumentTemplateResource::class,
            data: $template,
        );
    }
}
