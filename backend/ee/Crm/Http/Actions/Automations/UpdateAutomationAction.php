<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Automations;

use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\DomainObjects\Generated\CrmAutomationDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Http\Request\UpsertAutomationRequest;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation\UpsertAutomationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class UpdateAutomationAction extends BaseUpsertAutomationAction
{
    public function __construct(
        UpsertAutomationHandler $handler,
        private readonly CrmAutomationRepositoryInterface $automationRepository,
    ) {
        parent::__construct($handler);
    }

    public function __invoke(UpsertAutomationRequest $request, int $organizerId, int $automationId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        $automation = $this->automationRepository->findFirstWhere([
            CrmAutomationDomainObjectAbstract::ID => $automationId,
            CrmAutomationDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]);

        if ($automation === null) {
            return $this->errorResponse(__('Automation not found'), Response::HTTP_NOT_FOUND);
        }

        return $this->upsert($request, $organizerId, CrmAutomationTrigger::from($automation->getTrigger()), $automationId, Response::HTTP_OK);
    }
}
