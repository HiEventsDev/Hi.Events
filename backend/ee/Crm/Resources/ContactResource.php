<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmContactDomainObject
 */
class ContactResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'email' => $this->getEmail(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'full_name' => $this->getFullName(),
            'locale' => $this->getLocale(),
            /** @var 'ORDER'|'ATTENDEE'|'WAITLIST'|'IMPORT'|'MANUAL'|'FORM' */
            'source' => $this->getSource(),
            /** @var 'NOT_SUBSCRIBED'|'SUBSCRIBED'|'UNSUBSCRIBED'|'BOUNCED'|'COMPLAINED' */
            'email_marketing_status' => $this->getEmailMarketingStatus(),
            'email_marketing_status_changed_at' => $this->getEmailMarketingStatusChangedAt(),
            'phone' => $this->getPhone(),
            /** @var 'NOT_SUBSCRIBED'|'SUBSCRIBED'|'UNSUBSCRIBED' */
            'sms_marketing_status' => $this->getSmsMarketingStatus(),
            'orders_count' => $this->getOrdersCount(),
            'tickets_count' => $this->getTicketsCount(),
            'events_count' => $this->getEventsCount(),
            'attended_count' => $this->getAttendedCount(),
            'first_seen_at' => $this->getFirstSeenAt(),
            'last_order_at' => $this->getLastOrderAt(),
            'last_attended_at' => $this->getLastAttendedAt(),
            'last_activity_at' => $this->getLastActivityAt(),
            'created_at' => $this->getCreatedAt(),
            'tags' => ($this->getTags() ?? collect())->map(static fn (object $tag) => [
                'id' => (int) $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->values(),
            $this->mergeWhen($this->isPermitted(Permission::VIEW_FINANCIALS), fn () => [
                'spend' => ($this->getSpend() ?? collect())->map(static fn (object $spend) => [
                    'currency' => $spend->currency,
                    'total_gross' => (float) $spend->total_gross,
                    'total_refunded' => (float) $spend->total_refunded,
                    'net' => round((float) $spend->total_gross - (float) $spend->total_refunded, 2),
                ])->values(),
            ]),
            $this->mergeWhen($this->getSuppression() !== null, fn () => [
                'suppression' => new SuppressionDetailResource($this->getSuppression()),
            ]),
            $this->mergeWhen($this->getEmails() !== null, fn () => [
                'emails' => $this->getEmails()->map(fn (object $email) => [
                    'id' => (int) $email->id,
                    'email' => $email->email,
                    'is_primary' => $email->email === $this->getEmail(),
                    /** @var 'OPT_IN'|'OPT_OUT'|'BOUNCED'|'COMPLAINED'|null */
                    'consent_action' => $email->consent_action,
                    /** @var 'OPT_IN'|'OPT_OUT'|null */
                    'preference_action' => $email->preference_action,
                    'preference_at' => $email->preference_at,
                ])->values(),
                'names' => ($this->getNames() ?? collect())->map(static fn (object $name) => [
                    'first_name' => $name->first_name,
                    'last_name' => $name->last_name,
                    /** @var 'SELF'|'GUEST' */
                    'role' => $name->role,
                    'seen_count' => (int) $name->seen_count,
                ])->values(),
                'no_show_count' => $this->getNoShowCount() ?? 0,
            ]),
        ];
    }
}
