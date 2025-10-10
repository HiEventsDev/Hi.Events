<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\DomainObjects\Enums\EmailTemplateType;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<EmailTemplateDomainObject>
 */
interface EmailTemplateRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a template by type, with fallback logic
     * First tries event-specific, then organizer-specific, then system default
     */
    public function findByTypeWithFallback(
        EmailTemplateType $type,
        int $accountId,
        ?int $eventId = null,
        ?int $organizerId = null
    ): ?EmailTemplateDomainObject;

    /**
     * Find all templates for an event
     */
    public function findByEvent(int $eventId): Collection;

    /**
     * Find all templates for an organizer
     */
    public function findByOrganizer(int $organizerId): Collection;

    /**
     * Find a specific template
     */
    public function findByTypeAndScope(
        EmailTemplateType $type,
        int $accountId,
        ?int $eventId = null,
        ?int $organizerId = null
    ): ?EmailTemplateDomainObject;
}