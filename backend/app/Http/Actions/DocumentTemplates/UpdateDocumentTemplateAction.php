<?php

namespace HiEvents\Http\Actions\DocumentTemplates;

use HiEvents\DomainObjects\Generated\DocumentTemplateDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\DocumentTemplateRepositoryInterface;
use HiEvents\Resources\DocumentTemplate\DocumentTemplateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateDocumentTemplateAction extends BaseAction
{
    public function __construct(
        private readonly DocumentTemplateRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(Request $request, int $templateId): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:CERTIFICATE,RECEIPT,BADGE,CUSTOM',
            'content' => 'sometimes|string|max:65535',
            'event_id' => 'nullable|integer',
            'settings' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        if (isset($validated['settings'])) {
            $validated['settings'] = json_encode($validated['settings']);
        }

        $template = $this->repository->updateByIdWhere(
            id: $templateId,
            attributes: $validated,
            where: [
                [DocumentTemplateDomainObjectAbstract::ACCOUNT_ID, '=', $accountId],
            ],
        );

        return $this->resourceResponse(
            resource: DocumentTemplateResource::class,
            data: $template,
        );
    }
}
