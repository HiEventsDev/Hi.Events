<?php

namespace HiEvents\Services\Domain\Abuse;

use HiEvents\Mail\Admin\SuspiciousActivityReportMail;
use HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityFindingDTO;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

class SuspiciousActivityReportService
{
    private const REPORTED_TTL_SECONDS = 86400;

    public function __construct(
        private readonly SuspiciousActivityDetector $detector,
        private readonly CacheRepository $cache,
        private readonly Mailer $mailer,
        private readonly Repository $config,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return Collection<int, SuspiciousActivityFindingDTO>
     */
    public function reportNewFindings(): Collection
    {
        $newFindings = $this->detector
            ->detect()
            ->reject(fn (SuspiciousActivityFindingDTO $finding) => $this->cache->has($this->reportedKey($finding)))
            ->values();

        if ($newFindings->isEmpty()) {
            return $newFindings;
        }

        $this->logger->warning('Suspicious activity detected', [
            'findings' => $newFindings->map(fn (SuspiciousActivityFindingDTO $finding) => [
                'type' => $finding->type,
                'summary' => $finding->summary,
                'details' => $finding->details,
            ])->all(),
        ]);

        $recipient = $this->config->get('app.platform_support_email');

        if ($recipient) {
            $this->mailer->to($recipient)->send(new SuspiciousActivityReportMail($newFindings->all()));
        }

        $newFindings->each(fn (SuspiciousActivityFindingDTO $finding) => $this->cache->put(
            $this->reportedKey($finding),
            true,
            self::REPORTED_TTL_SECONDS,
        ));

        return $newFindings;
    }

    private function reportedKey(SuspiciousActivityFindingDTO $finding): string
    {
        return 'suspicious-activity-reported:'.hash('sha256', $finding->fingerprint);
    }
}
