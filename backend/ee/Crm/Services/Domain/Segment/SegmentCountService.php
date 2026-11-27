<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Segment;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO\SegmentCountDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;

class SegmentCountService
{
    private const CACHE_SECONDS = 300;

    public function __construct(
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly SegmentQueryCompiler $compiler,
        private readonly CrmCacheVersion $cacheVersion,
    ) {}

    public function count(int $organizerId, ?array $definition): SegmentCountDTO
    {
        [$contacts, $mailable, $textable] = $this->cacheVersion->remember(
            $organizerId,
            'segment-count:'.sha1((string) json_encode($definition)),
            self::CACHE_SECONDS,
            function () use ($organizerId, $definition): array {
                $counts = $this->contactRepository->countSegment(
                    $organizerId,
                    $definition === null ? null : $this->compiler->compile($definition, $organizerId, now()),
                );

                return [(int) $counts->contacts_count, (int) $counts->mailable_count, (int) $counts->textable_count];
            },
        );

        return new SegmentCountDTO(contacts_count: $contacts, mailable_count: $mailable, textable_count: $textable);
    }

    /**
     * @param  string[]  $countries
     */
    public function countTextableIn(int $organizerId, ?array $definition, array $countries): int
    {
        if ($countries === []) {
            return 0;
        }

        sort($countries);

        return (int) $this->cacheVersion->remember(
            $organizerId,
            'segment-textable:'.implode(',', $countries).':'.sha1((string) json_encode($definition)),
            self::CACHE_SECONDS,
            fn (): int => $this->contactRepository->countTextable(
                $organizerId,
                $definition === null ? null : $this->compiler->compile($definition, $organizerId, now()),
                $countries,
            ),
        );
    }
}
