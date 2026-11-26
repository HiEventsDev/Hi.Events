<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Insight;

use Illuminate\Support\Collection;

class ContactSummaryMapper
{
    /**
     * @param  Collection<int, object>  $contacts
     * @return array<int, array<string, mixed>>
     */
    public function map(Collection $contacts): array
    {
        return $contacts->map(static fn (object $contact) => [
            'id' => (int) $contact->id,
            'email' => $contact->email,
            'full_name' => trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')),
            'events_count' => (int) $contact->events_count,
            'orders_count' => (int) $contact->orders_count,
            'attended_count' => (int) $contact->attended_count,
            'last_order_at' => $contact->last_order_at,
        ])->all();
    }
}
