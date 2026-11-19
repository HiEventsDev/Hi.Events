<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Templates;

use HiEvents\DomainObjects\Generated\CrmCampaignTemplateDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignTemplateRepositoryInterface;
use Illuminate\Http\Response;

class DeleteCampaignTemplateAction extends BaseCrmAction
{
    public function __construct(
        private readonly CrmCampaignTemplateRepositoryInterface $templateRepository,
    ) {}

    public function __invoke(int $organizerId, int $templateId): Response
    {
        $this->authorizeCrm($organizerId);

        $this->templateRepository->deleteWhere([
            CrmCampaignTemplateDomainObjectAbstract::ID => $templateId,
            CrmCampaignTemplateDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]);

        return $this->deletedResponse();
    }
}
