<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum CrmCampaignStatus: string
{
    use BaseEnum;

    case DRAFT = 'DRAFT';
    case SCHEDULED = 'SCHEDULED';
    case PENDING_REVIEW = 'PENDING_REVIEW';
    case SENDING = 'SENDING';
    case SENT = 'SENT';
    case PAUSED = 'PAUSED';
    case CANCELLED = 'CANCELLED';
    case REJECTED = 'REJECTED';

    /**
     * @return self[]
     */
    public static function editable(): array
    {
        return [self::DRAFT, self::REJECTED];
    }

    /**
     * @return self[]
     */
    public static function launchable(): array
    {
        return self::editable();
    }

    /**
     * @return self[]
     */
    public static function resolvingAudienceLater(): array
    {
        return [...self::editable(), self::SCHEDULED];
    }

    /**
     * @return self[]
     */
    public static function withdrawable(): array
    {
        return [self::SCHEDULED, self::PENDING_REVIEW];
    }

    /**
     * @return self[]
     */
    public static function stoppable(): array
    {
        return [self::SENDING];
    }

    /**
     * @return self[]
     */
    public static function holdingPendingRecipients(): array
    {
        return [self::SENDING, self::PENDING_REVIEW];
    }

    /**
     * @return self[]
     */
    public static function launched(): array
    {
        return [self::SENDING, self::SENT, self::PAUSED, self::CANCELLED];
    }

    /**
     * @return string[]
     */
    public static function editableValues(): array
    {
        return self::valuesOf(self::editable());
    }

    /**
     * @param  self[]  $statuses
     * @return string[]
     */
    public static function valuesOf(array $statuses): array
    {
        return array_map(static fn (self $status) => $status->value, $statuses);
    }
}
