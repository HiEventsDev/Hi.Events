<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Status\BadgePrintStatus;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class BadgeDTO extends BaseDataObject
{
    /**
     * @param  BadgeLineDTO[]  $lines
     */
    public function __construct(
        public int $attendee_id,
        public string $attendee_public_id,
        public int $order_id,
        public int $product_id,
        public ?int $event_occurrence_id,
        public string $status,
        public ?string $first_name,
        public ?string $last_name,
        #[DataCollectionOf(BadgeLineDTO::class)]
        public array $lines,
        public ?BadgeRibbonDTO $ribbon,
        public ?string $seat_label,
        public ?string $badge_printed_at,
        public int $badge_print_count,
        public BadgePrintStatus $print_status,
    ) {}
}
