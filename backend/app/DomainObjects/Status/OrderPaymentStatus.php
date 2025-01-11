<?php

namespace HiEvents\DomainObjects\Status;

enum OrderPaymentStatus
{
    case NO_PAYMENT_REQUIRED;
    case AWAITING_PAYMENT;
    case AWAITING_OFFLINE_PAYMENT;
    case PAYMENT_FAILED;
    case PAYMENT_RECEIVED;
}

