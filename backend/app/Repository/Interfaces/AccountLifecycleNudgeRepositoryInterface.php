<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\AccountLifecycleNudgeDomainObject;
use HiEvents\DomainObjects\Enums\LifecycleNudgeType;
use HiEvents\Repository\DTO\LifecycleEmail\AccountOwnerNudgeCandidateDTO;
use HiEvents\Repository\DTO\LifecycleEmail\EventNudgeCandidateDTO;
use HiEvents\Repository\DTO\LifecycleEmail\LifecycleNudgeSendCountsDTO;
use HiEvents\Repository\DTO\LifecycleEmail\StripeOnboardingNudgeCandidateDTO;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<AccountLifecycleNudgeDomainObject>
 */
interface AccountLifecycleNudgeRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, AccountOwnerNudgeCandidateDTO>
     */
    public function findNoEventCandidates(
        CarbonInterface $createdAfter,
        CarbonInterface $createdBefore,
        int $limit,
    ): Collection;

    /**
     * @param  array<int, string>  $excludedTimezones
     * @return Collection<int, StripeOnboardingNudgeCandidateDTO>
     */
    public function findStripeOnboardingCandidates(
        CarbonInterface $triggeredAfter,
        CarbonInterface $triggeredBefore,
        array $excludedTimezones,
        int $limit,
    ): Collection;

    /**
     * @return Collection<int, EventNudgeCandidateDTO>
     */
    public function findDraftEventCandidates(
        CarbonInterface $createdAfter,
        CarbonInterface $createdBefore,
        int $limit,
    ): Collection;

    /**
     * @return Collection<int, EventNudgeCandidateDTO>
     */
    public function findLiveEventWithoutSalesCandidates(
        CarbonInterface $readyAfter,
        CarbonInterface $readyBefore,
        CarbonInterface $upcomingAfter,
        int $limit,
    ): Collection;

    public function claim(int $accountId, LifecycleNudgeType $nudgeType, ?int $eventId = null): bool;

    public function release(int $accountId, LifecycleNudgeType $nudgeType): void;

    public function recordProviderMessageId(int $accountId, LifecycleNudgeType $nudgeType, string $providerMessageId): void;

    /**
     * @return array<string, LifecycleNudgeSendCountsDTO>
     */
    public function countsByType(CarbonInterface $recentSince): array;
}
