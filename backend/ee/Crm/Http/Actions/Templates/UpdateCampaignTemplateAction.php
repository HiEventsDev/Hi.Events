<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Templates;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\UpsertCampaignTemplateRequest;
use HiEvents\Enterprise\Crm\Resources\CampaignTemplateResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Template\DTO\UpsertCampaignTemplateDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Template\UpsertCampaignTemplateHandler;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class UpdateCampaignTemplateAction extends BaseCrmAction
{
    public function __construct(
        private readonly UpsertCampaignTemplateHandler $handler,
    ) {}

    public function __invoke(UpsertCampaignTemplateRequest $request, int $organizerId, int $templateId): JsonResponse
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
                template_id: $templateId,
            ));
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return $this->resourceResponse(CampaignTemplateResource::class, $template);
    }
}
