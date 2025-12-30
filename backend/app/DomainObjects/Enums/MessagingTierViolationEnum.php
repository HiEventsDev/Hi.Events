<?php

namespace HiEvents\DomainObjects\Enums;

enum MessagingTierViolationEnum: string
{
    case MESSAGE_LIMIT_EXCEEDED = 'message_limit_exceeded';
    case RECIPIENT_LIMIT_EXCEEDED = 'recipient_limit_exceeded';
    case LINKS_NOT_ALLOWED = 'links_not_allowed';

    public function getMessage(): string
    {
        return match ($this) {
            self::MESSAGE_LIMIT_EXCEEDED => __('You have reached your daily message limit. Please try again later or contact support to increase your limits.'),
            self::RECIPIENT_LIMIT_EXCEEDED => __('The number of recipients exceeds your account limit. Please contact support to increase your limits.'),
            self::LINKS_NOT_ALLOWED => __('Your account tier does not allow links in messages. Please contact support to enable this feature.'),
        };
    }
}
