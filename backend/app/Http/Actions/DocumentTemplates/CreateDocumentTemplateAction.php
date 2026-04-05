<?php

namespace HiEvents\Http\Actions\DocumentTemplates;

use HiEvents\DomainObjects\Generated\DocumentTemplateDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Repository\Interfaces\DocumentTemplateRepositoryInterface;
use HiEvents\Resources\DocumentTemplate\DocumentTemplateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreateDocumentTemplateAction extends BaseAction
{
    public function __construct(
        private readonly DocumentTemplateRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:CERTIFICATE,RECEIPT,BADGE,CUSTOM',
            'content' => 'required|string|max:65535',
            'event_id' => 'nullable|integer',
            'settings' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        $template = $this->repository->create([
            DocumentTemplateDomainObjectAbstract::ACCOUNT_ID => $accountId,
            DocumentTemplateDomainObjectAbstract::EVENT_ID => $validated['event_id'] ?? null,
            DocumentTemplateDomainObjectAbstract::NAME => $validated['name'],
            DocumentTemplateDomainObjectAbstract::TYPE => $validated['type'],
            DocumentTemplateDomainObjectAbstract::CONTENT => $validated['content'],
            DocumentTemplateDomainObjectAbstract::SETTINGS => json_encode($validated['settings'] ?? []),
            DocumentTemplateDomainObjectAbstract::IS_DEFAULT => $validated['is_default'] ?? false,
        ]);

        return $this->resourceResponse(
            resource: DocumentTemplateResource::class,
            data: $template,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
