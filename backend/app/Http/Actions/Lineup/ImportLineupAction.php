<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Lineup;

use HiEvents\DomainObjects\Enums\LineupBillingTier;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Lineup\ImportLineupRequest;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Lineup\DTO\ImportLineupDTO;
use HiEvents\Services\Application\Handlers\Lineup\DTO\ImportLineupPersonDTO;
use HiEvents\Services\Application\Handlers\Lineup\DTO\ImportLineupSlotDTO;
use HiEvents\Services\Application\Handlers\Lineup\ImportLineupHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class ImportLineupAction extends BaseAction
{
    public function __construct(
        private readonly ImportLineupHandler $handler,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(int $eventId, ImportLineupRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->isActionAuthorized($this->eventRepository->findById($eventId)->getOrganizerId(), OrganizerDomainObject::class);

        $result = $this->handler->handle(new ImportLineupDTO(
            event_id: $eventId,
            people: array_map(fn (array $person) => new ImportLineupPersonDTO(
                name: $person['name'],
                tagline: $person['tagline'] ?? null,
                bio: $person['bio'] ?? null,
                links: $person['links'] ?? [],
                billing_tier: isset($person['billing_tier']) ? LineupBillingTier::from($person['billing_tier']) : null,
                role_label: $person['role_label'] ?? null,
                is_hidden: isset($person['is_hidden']) ? (bool) $person['is_hidden'] : null,
            ), $request->validated('people')),
            slots: array_map(fn (array $slot) => new ImportLineupSlotDTO(
                starts_at: $slot['starts_at'],
                ends_at: $slot['ends_at'],
                stage: $slot['stage'] ?? null,
                title: $slot['title'] ?? null,
                description: $slot['description'] ?? null,
                people: $slot['people'] ?? [],
            ), $request->validated('slots')),
        ));

        return $this->jsonResponse($result->toArray());
    }
}
