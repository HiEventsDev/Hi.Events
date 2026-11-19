<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\DomainObjects\Generated\CrmAutomationDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunAutomationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 40;

    public int $uniqueFor = AutomationRunner::RUN_INTERVAL_MINUTES * 60;

    public function __construct(
        public int $automationId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->automationId;
    }

    public function handle(CrmAutomationRepositoryInterface $automationRepository, AutomationRunner $runner): void
    {
        $automation = $automationRepository->findFirstWhere([
            CrmAutomationDomainObjectAbstract::ID => $this->automationId,
            CrmAutomationDomainObjectAbstract::IS_ENABLED => true,
        ]);

        if ($automation === null) {
            return;
        }

        try {
            $runner->run($automation);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
