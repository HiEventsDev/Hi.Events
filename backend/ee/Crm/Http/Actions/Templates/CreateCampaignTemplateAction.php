<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Templates;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\UpsertCampaignTemplateRequest;
use HiEvents\Enterprise\Crm\Resources\CampaignTemplateResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Template\DTO\UpsertCampaignTemplateDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Template\UpsertCampaignTemplateHandler;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CreateCampaignTemplateAction extends BaseCrmAction
{
    public function __construct(
        private readonly UpsertCampaignTemplateHandler $handler,
    ) {}

    public function __invoke(UpsertCampaignTemplateRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $template = $this->handler->handle(new UpsertCampaignTemplateDTO(
                account_id: $this->getAuthenticatedAccountId(),
                organizer_id: $organizerId,
                name: $request->validated('name'),
                subject: $request->validated('subject'),
                preheader: $request->validated('preheader'),
                body: $request->validated('body'),
                template_id: null,
            ));
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return $this->resourceResponse(CampaignTemplateResource::class, $template, Response::HTTP_CREATED);
    }
}
