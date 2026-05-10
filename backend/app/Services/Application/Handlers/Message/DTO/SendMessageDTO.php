<?php

namespace HiEvents\Services\Application\Handlers\Message\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Enums\MessageTypeEnum;

class SendMessageDTO extends BaseDTO
{
    public function __construct(
        public readonly int $account_id,
        public readonly int $event_id,
        public readonly string $subject,
        public readonly string $message,
        public readonly MessageTypeEnum $type,
        public readonly bool $is_test,
        public readonly bool $send_copy_to_current_user,
        public readonly int $sent_by_user_id,
        public readonly ?int $order_id = null,
        public readonly ?array $order_statuses = [],
        public readonly ?int $id = null,
        public readonly ?array $attendee_ids = [],
        public readonly ?array $product_ids = [],
        public readonly ?string $scheduled_at = null,
        public readonly ?int $event_occurrence_id = null,
        /**
         * When set, filters recipients to attendees/orders tied to any of these
         * occurrences. Mutually exclusive with event_occurrence_id (handler
         * prefers this when both are provided).
         */
        public readonly ?array $event_occurrence_ids = null,
    ) {}
}
