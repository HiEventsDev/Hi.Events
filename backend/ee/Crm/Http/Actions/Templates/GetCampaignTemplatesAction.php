<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Templates;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignTemplateRepositoryInterface;
use HiEvents\Enterprise\Crm\Resources\CampaignTemplateResource;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignStarterTemplates;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignStarterDTO;
use Illuminate\Http\JsonResponse;

class GetCampaignTemplatesAction extends BaseCrmAction
{
    public function __construct(
        private readonly CrmCampaignTemplateRepositoryInterface $templateRepository,
        private readonly CampaignStarterTemplates $starters,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->jsonResponse([
            'data' => CampaignTemplateResource::collection($this->templateRepository->findForOrganizer($organizerId)),
            'starters' => array_map(static fn (CampaignStarterDTO $starter) => $starter->toArray(), $this->starters->all()),
        ]);
    }
}
