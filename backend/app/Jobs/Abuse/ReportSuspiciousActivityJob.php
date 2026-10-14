<?php

namespace HiEvents\Jobs\Abuse;

use HiEvents\Services\Domain\Abuse\SuspiciousActivityReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReportSuspiciousActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(SuspiciousActivityReportService $reportService): void
    {
        $reportService->reportNewFindings();
    }
}
