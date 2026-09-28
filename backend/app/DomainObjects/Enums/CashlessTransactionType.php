<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects\Enums;

enum CashlessTransactionType: string
{
    use BaseEnum;

    case TOPUP_ONLINE = 'TOPUP_ONLINE';
    case TOPUP_STAFF = 'TOPUP_STAFF';
    case PURCHASE = 'PURCHASE';
    case REVERSAL = 'REVERSAL';
    case REFUND_REMAINING = 'REFUND_REMAINING';
    case CLOSURE = 'CLOSURE';

    public function isCredit(): bool
    {
        return in_array($this, [self::TOPUP_ONLINE, self::TOPUP_STAFF], true);
    }

    public static function getHumanReadableType(string $type): string
    {
        return match ($type) {
            self::TOPUP_ONLINE->value => __('Online top-up'),
            self::TOPUP_STAFF->value => __('Staff top-up'),
            self::PURCHASE->value => __('Purchase'),
            self::REVERSAL->value => __('Reversal'),
            self::REFUND_REMAINING->value => __('Balance refund'),
            self::CLOSURE->value => __('Balance closure'),
        };
    }
}
