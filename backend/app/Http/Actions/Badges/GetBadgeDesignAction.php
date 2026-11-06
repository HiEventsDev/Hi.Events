<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Badges;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Badge\BadgeDesignResource;
use HiEvents\Services\Application\Handlers\Badge\GetBadgeDesignHandler;
use Illuminate\Http\JsonResponse;

class GetBadgeDesignAction extends BaseAction
{
    public function __construct(
        private readonly GetBadgeDesignHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: BadgeDesignResource::class,
            data: $this->handler->handle($eventId),
        );
    }
}
