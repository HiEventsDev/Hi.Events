<?php

namespace HiEvents\Services\Application\Handlers\Message\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Enums\MessageTypeEnum;

class SendMessageDTO extends BaseDTO
{
    public function __construct(
        public readonly int             $account_id,
        public readonly int             $event_id,
        public readonly string          $subject,
        public readonly string          $message,
        public readonly MessageTypeEnum $type,
        public readonly bool            $is_test,
        public readonly bool            $send_copy_to_current_user,
        public readonly int             $sent_by_user_id,
        public readonly ?array          $order_statuses = [],
        public readonly ?int            $order_id,
        public readonly ?int            $id = null,
        public readonly ?array          $attendee_ids = [],
        public readonly ?array          $product_ids = [],
    )
    {
    }
}
