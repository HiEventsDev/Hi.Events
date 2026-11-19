<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmTransportVerdict
{
    case NOT_ACCEPTED;
    case MAYBE_ACCEPTED;
    case REFUSED;
    case ADDRESS_REFUSED;

    public function failureReason(): ?CrmRecipientFailureReason
    {
        return match ($this) {
            self::NOT_ACCEPTED => null,
            self::MAYBE_ACCEPTED => CrmRecipientFailureReason::TIMEOUT,
            self::REFUSED => CrmRecipientFailureReason::PROVIDER_REJECTED,
            self::ADDRESS_REFUSED => CrmRecipientFailureReason::PROVIDER_SUPPRESSED,
        };
    }
}
