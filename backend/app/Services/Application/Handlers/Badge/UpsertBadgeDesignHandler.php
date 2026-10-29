<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Badge;

use HiEvents\Exceptions\InvalidBadgeDesignException;
use HiEvents\Services\Application\Handlers\Badge\DTO\BadgeDesignResultDTO;
use HiEvents\Services\Domain\Badge\BadgeDesignService;
use HiEvents\Services\Domain\Badge\DTO\BadgeDesignDTO;

class UpsertBadgeDesignHandler
{
    public function __construct(
        private readonly BadgeDesignService $badgeDesignService,
    ) {}

    /**
     * @throws InvalidBadgeDesignException
     */
    public function handle(int $eventId, BadgeDesignDTO $design): BadgeDesignResultDTO
    {
        return new BadgeDesignResultDTO(
            design: $this->badgeDesignService->save($eventId, $design),
            is_customized: true,
        );
    }
}
