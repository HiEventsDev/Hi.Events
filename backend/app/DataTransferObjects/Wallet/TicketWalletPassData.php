<?php

declare(strict_types=1);

namespace HiEvents\DataTransferObjects\Wallet;

use HiEvents\DataTransferObjects\BaseDataObject;

class TicketWalletPassData extends BaseDataObject
{
    public function __construct(
        public readonly int $eventId,
        public readonly string $serialNumber,
        public readonly string $eventTitle,
        public readonly string $organizerName,
        public readonly string $ticketTitle,
        public readonly string $attendeeName,
        public readonly string $barcodeValue,
        public readonly ?string $startDate,
        public readonly ?string $endDate,
        public readonly string $timezone,
        public readonly string $ticketUrl,
    ) {}
}
