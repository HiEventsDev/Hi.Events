<?php

namespace HiEvents\Repository\DTO\ProductPurchase;

use HiEvents\DataTransferObjects\BaseDataObject;

class ProductPurchaseDTO extends BaseDataObject
{
    public function __construct(
        public int $orderId,
        public string $orderPublicId,
        public string $orderStatus,
        public ?string $refundStatus,
        public string $orderCreatedAt,
        public string $currency,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $email,
        public string $productTitle,
        public ?int $productPriceId,
        public ?string $priceLabel,
        public ?int $eventOccurrenceId,
        public ?string $occurrenceStartDate,
        public int $soldQuantity,
        public int $awaitingPaymentQuantity,
        public int $cancelledQuantity,
        public ?float $lineTotal,
        public string $status,
    ) {}
}
