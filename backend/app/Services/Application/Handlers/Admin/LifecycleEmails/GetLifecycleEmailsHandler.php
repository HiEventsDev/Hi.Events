<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\LifecycleEmails;

use HiEvents\Repository\DTO\LifecycleEmail\LifecycleNudgeSendCountsDTO;
use HiEvents\Repository\Interfaces\AccountLifecycleNudgeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\DTO\AdminLifecycleEmailDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailTemplateDTO;
use HiEvents\Services\Domain\LifecycleEmail\LifecycleEmailTemplateService;
use HiEvents\Services\Domain\LifecycleEmail\Nudges\LifecycleNudgeRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetLifecycleEmailsHandler
{
    public function __construct(
        private readonly LifecycleEmailTemplateService $templateService,
        private readonly AccountLifecycleNudgeRepositoryInterface $nudgeRepository,
        private readonly LifecycleNudgeRegistry $nudgeRegistry,
    ) {}

    /**
     * @return Collection<int, AdminLifecycleEmailDTO>
     */
    public function handle(): Collection
    {
        $counts = $this->nudgeRepository->countsByType(Carbon::now()->subDays(30));

        return $this->templateService->findAll()->map(
            fn (LifecycleEmailTemplateDTO $template) => $this->toAdminDTO($template, $counts[$template->nudgeType->name] ?? null),
        );
    }

    private function toAdminDTO(LifecycleEmailTemplateDTO $template, ?LifecycleNudgeSendCountsDTO $counts): AdminLifecycleEmailDTO
    {
        return new AdminLifecycleEmailDTO(
            template: $template,
            sentTotal: $counts?->total ?? 0,
            sentLastThirtyDays: $counts?->lastThirtyDays ?? 0,
            delivered: $counts?->delivered ?? 0,
            bounced: $counts?->bounced ?? 0,
            opened: $counts?->opened ?? 0,
            clicked: $counts?->clicked ?? 0,
            tokens: $this->nudgeRegistry->for($template->nudgeType)->tokens(),
        );
    }
}
