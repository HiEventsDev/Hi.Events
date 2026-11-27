<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Consent;

use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\CrmRecipientFailureReason;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;

final class ConsentStatusSql
{
    public static function complaintStands(string $event): string
    {
        return "{$event}.action = '".CrmConsentAction::COMPLAINED->value."' AND ".ConsentSupersedeSql::notSuperseded($event);
    }

    public static function bounceStands(string $event): string
    {
        return "{$event}.action = '".CrmConsentAction::BOUNCED->value."' AND ".ConsentSupersedeSql::bounceStands($event);
    }

    public static function status(string $event, string $onPrimaryAddress = 'true'): string
    {
        return 'CASE
            WHEN coalesce(bool_or('.self::complaintStands($event)."), false) THEN '".CrmEmailMarketingStatus::COMPLAINED->value."'
            WHEN coalesce(bool_or(($onPrimaryAddress) AND ".self::bounceStands($event)."), false) THEN '".CrmEmailMarketingStatus::BOUNCED->value."'
            ELSE coalesce(".self::latestChoice($event, $onPrimaryAddress).", '".CrmEmailMarketingStatus::NOT_SUBSCRIBED->value."')
        END";
    }

    public static function suppressionOnAnyAddress(string $event, string $onPrimaryAddress): string
    {
        return 'CASE
            WHEN coalesce(bool_or('.self::complaintStands($event)."), false) THEN '".CrmEmailMarketingStatus::COMPLAINED->value."'
            ELSE nullif(".self::latestChoice($event, $onPrimaryAddress).", '".CrmEmailMarketingStatus::SUBSCRIBED->value."')
        END";
    }

    private static function latestChoice(string $event, string $onPrimaryAddress): string
    {
        $optIn = CrmConsentAction::OPT_IN->value;
        $optOut = CrmConsentAction::OPT_OUT->value;

        return "(array_agg(
            CASE {$event}.action WHEN '$optIn' THEN '".CrmEmailMarketingStatus::SUBSCRIBED->value."' ELSE '".CrmEmailMarketingStatus::UNSUBSCRIBED->value."' END
            ORDER BY {$event}.occurred_at DESC, {$event}.id DESC
        ) FILTER (WHERE {$event}.action = '$optOut' OR ({$event}.action = '$optIn' AND ($onPrimaryAddress))))[1]";
    }

    public static function changedAt(string $event, string $onPrimaryAddress = 'true'): string
    {
        $anyAddress = "'".CrmConsentAction::COMPLAINED->value."', '".CrmConsentAction::OPT_OUT->value."'";

        return "max({$event}.occurred_at) FILTER (WHERE {$event}.action IN ($anyAddress) OR ($onPrimaryAddress))";
    }

    public static function providerRejectedAt(string $contactColumn, string $emailColumn): string
    {
        return "SELECT max(rejected.occurred_at)
            FROM crm_consent_events rejected
            JOIN crm_campaign_recipients rejected_recipient ON rejected_recipient.id = rejected.source_id
            WHERE rejected.contact_id = $contactColumn AND rejected.email = $emailColumn
              AND rejected.action = '".CrmConsentAction::BOUNCED->value."'
              AND rejected.source_type = '".CrmConsentSource::CAMPAIGN->value."'
              AND rejected_recipient.failure_reason = '".CrmRecipientFailureReason::PROVIDER_SUPPRESSED->value."'";
    }
}
