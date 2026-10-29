<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail\Nudges;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Enums\LifecycleNudgeType;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailRecipientDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailTemplateDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleNudgeCandidateDTO;
use Illuminate\Support\Collection;

interface LifecycleNudgeInterface
{
    public function type(): LifecycleNudgeType;

    /**
     * @return Collection<int, LifecycleNudgeCandidateDTO>
     */
    public function findCandidates(CarbonInterface $triggeredAfter, int $limit): Collection;

    public function defaultTemplate(): LifecycleEmailTemplateDTO;

    /**
     * @return array<int, array{token: string, description: string, example: string}>
     */
    public function tokens(): array;

    public function sampleRecipient(): LifecycleEmailRecipientDTO;
}
