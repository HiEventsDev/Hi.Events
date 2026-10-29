<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Badge;

use HiEvents\Services\Application\Handlers\Badge\DTO\BadgeDesignResultDTO;
use HiEvents\Services\Domain\Badge\BadgeDesignService;

class GetBadgeDesignHandler
{
    public function __construct(
        private readonly BadgeDesignService $badgeDesignService,
    ) {}

    public function handle(int $eventId): BadgeDesignResultDTO
    {
        return new BadgeDesignResultDTO(
            design: $this->badgeDesignService->getDesign($eventId),
            is_customized: $this->badgeDesignService->isCustomized($eventId),
        );
    }
}
