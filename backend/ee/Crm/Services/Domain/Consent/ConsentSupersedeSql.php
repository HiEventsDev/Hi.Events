<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Consent;

use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;

final class ConsentSupersedeSql
{
    public static function notSuperseded(string $event): string
    {
        return "NOT EXISTS (SELECT 1 FROM crm_consent_events verified WHERE verified.contact_id = {$event}.contact_id"
            ." AND verified.email = {$event}.email"
            ." AND verified.action = '".CrmConsentAction::OPT_IN->value."'"
            ." AND verified.source_type = '".CrmConsentSource::FORM->value."'"
            ." AND (verified.occurred_at, verified.id) > ({$event}.occurred_at, {$event}.id))";
    }

    public static function bounceStands(string $event): string
    {
        return self::notSuperseded($event)
            ." AND NOT EXISTS (SELECT 1 FROM crm_consent_events cleared WHERE cleared.contact_id = {$event}.contact_id"
            ." AND cleared.email = {$event}.email"
            ." AND cleared.action = '".CrmConsentAction::BOUNCE_CLEARED->value."'"
            ." AND (cleared.occurred_at, cleared.id) > ({$event}.occurred_at, {$event}.id))";
    }
}
