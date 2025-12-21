<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Models\EmailTemplate;
use HiEvents\Repository\Interfaces\EmailTemplateRepositoryInterface;
use Illuminate\Support\Collection;

class EmailTemplateRepository extends BaseRepository implements EmailTemplateRepositoryInterface
{
    protected function getModel(): string
    {
        return EmailTemplate::class;
    }

    public function getDomainObject(): string
    {
        return EmailTemplateDomainObject::class;
    }

    public function findByTypeWithFallback(
        EmailTemplateType $type,
        int $accountId,
        ?int $eventId = null,
        ?int $organizerId = null
    ): ?EmailTemplateDomainObject {
        // Try event-specific template first
        if ($eventId) {
            $template = $this->findByTypeAndScope($type, $accountId, $eventId);
            if ($template) {
                return $template;
            }
        }

        // Try organizer-specific template as fallback
        if ($organizerId) {
            $template = $this->findByTypeAndScope($type, $accountId, null, $organizerId);
            if ($template) {
                return $template;
            }
        }

        // No custom template found - AttendeeTicketMail and OrderSummary will use their default templates
        return null;
    }

    public function findByEvent(int $eventId): Collection
    {
        return $this->findWhere([
            'event_id' => $eventId,
            'is_active' => true,
        ]);
    }

    public function findByOrganizer(int $organizerId): Collection
    {
        return $this->findWhere([
            'organizer_id' => $organizerId,
            'event_id' => null,
            'is_active' => true,
        ]);
    }

    public function findByTypeAndScope(
        EmailTemplateType $type,
        int $accountId,
        ?int $eventId = null,
        ?int $organizerId = null
    ): ?EmailTemplateDomainObject {
        $conditions = [
            'account_id' => $accountId,
            'template_type' => $type->value,
            'is_active' => true,
        ];

        if ($eventId) {
            $conditions['event_id'] = $eventId;
        } else {
            $conditions[] = ['event_id', '=', null];
        }

        if ($organizerId) {
            $conditions['organizer_id'] = $organizerId;
        } else {
            $conditions[] = ['organizer_id', '=', null];
        }

        return $this->findFirstWhere($conditions);
    }
}
