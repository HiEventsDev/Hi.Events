<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Exceptions;

use Exception;

class CampaignNotSendableException extends Exception
{
    public const REASON_NOT_SENDABLE = 'NOT_SENDABLE';

    public const REASON_ACCOUNT_NOT_VERIFIED = 'ACCOUNT_NOT_VERIFIED';

    public const REASON_ACCOUNT_NOT_APPROVED = 'ACCOUNT_NOT_APPROVED';

    public const REASON_TOO_MANY_RECIPIENTS = 'TOO_MANY_RECIPIENTS';

    public const REASON_LINKS_NOT_ALLOWED = 'LINKS_NOT_ALLOWED';

    public const REASON_EMPTY_CONTENT = 'EMPTY_CONTENT';

    public const REASON_INVALID_PLACEHOLDER = 'INVALID_PLACEHOLDER';

    public const REASON_SEGMENT_DELETED = 'SEGMENT_DELETED';

    /**
     * @param  array<string, int|string>  $reasonParams
     */
    public function __construct(
        string $message,
        public readonly string $reasonCode = self::REASON_NOT_SENDABLE,
        public readonly array $reasonParams = [],
    ) {
        parent::__construct($message);
    }
}
