<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Badges;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\InvalidBadgeDesignException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Badge\UpsertBadgeDesignRequest;
use HiEvents\Resources\Badge\BadgeDesignResource;
use HiEvents\Services\Application\Handlers\Badge\UpsertBadgeDesignHandler;
use HiEvents\Services\Domain\Badge\DTO\BadgeDesignDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpsertBadgeDesignAction extends BaseAction
{
    public function __construct(
        private readonly UpsertBadgeDesignHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(int $eventId, UpsertBadgeDesignRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $result = $this->handler->handle($eventId, BadgeDesignDTO::from($request->validated()));
        } catch (InvalidBadgeDesignException $exception) {
            throw ValidationException::withMessages([$exception->getField() => $exception->getMessage()]);
        }

        return $this->resourceResponse(
            resource: BadgeDesignResource::class,
            data: $result,
        );
    }
}
