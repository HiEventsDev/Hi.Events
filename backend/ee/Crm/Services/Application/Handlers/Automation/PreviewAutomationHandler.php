<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation;

use HiEvents\Enterprise\Crm\Exceptions\CampaignOrganizerDeletedException;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationPreviewService;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationPreviewDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\PreviewAutomationDTO;
use HiEvents\Exceptions\ResourceNotFoundException;

class PreviewAutomationHandler
{
    public function __construct(
        private readonly AutomationPreviewService $previewService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     * @throws CampaignOrganizerDeletedException
     */
    public function handle(PreviewAutomationDTO $dto): AutomationPreviewDTO
    {
        return $this->previewService->preview($dto);
    }
}
