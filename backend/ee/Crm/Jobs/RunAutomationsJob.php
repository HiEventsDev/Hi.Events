<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationRunner;
use HiEvents\Services\Domain\Account\AccountSuspensionService;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunAutomationsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const PAGE_SIZE = 500;

    private const AUTOMATIONS_PER_SECOND = 10;

    public int $tries = 1;

    public int $timeout = 40;

    public int $uniqueFor = AutomationRunner::RUN_INTERVAL_MINUTES * 60;

    public function __construct(
        public int $afterAutomationId = 0,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->afterAutomationId;
    }

    public function handle(
        CrmAutomationRepositoryInterface $automationRepository,
        FeatureFlagService $featureFlagService,
        AccountSuspensionService $accountSuspensionService,
    ): void {
        $accountIdsByAutomationId = $automationRepository->findEnabledAccountIdsByIdAfter($this->afterAutomationId, self::PAGE_SIZE);
        $maySendByAccountId = [];
        $position = 0;

        foreach ($accountIdsByAutomationId as $automationId => $accountId) {
            $maySendByAccountId[$accountId] ??= $featureFlagService->isEnabled(FeatureFlag::CRM, $accountId)
                && ! $accountSuspensionService->isAccountSuspended($accountId);

            if (! $maySendByAccountId[$accountId]) {
                continue;
            }

            RunAutomationJob::dispatch((int) $automationId)
                ->delay(now()->addSeconds(intdiv($position++, self::AUTOMATIONS_PER_SECOND)));
        }

        if ($accountIdsByAutomationId->count() === self::PAGE_SIZE) {
            self::dispatch((int) $accountIdsByAutomationId->keys()->last())
                ->delay(now()->addSeconds(intdiv($position, self::AUTOMATIONS_PER_SECOND)));
        }
    }
}
