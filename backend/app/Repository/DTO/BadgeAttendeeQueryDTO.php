<?php

declare(strict_types=1);

namespace HiEvents\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BadgeAttendeeQueryDTO extends BaseDataObject
{
    public const MAX_PER_PAGE = 1000;

    public const SORT_LAST_NAME = 'last_name';

    public const SORT_FIRST_NAME = 'first_name';

    public const SORT_PRODUCT = 'product';

    public const SORT_CREATED_AT = 'created_at';

    public const SORTS = [
        self::SORT_LAST_NAME,
        self::SORT_FIRST_NAME,
        self::SORT_PRODUCT,
        self::SORT_CREATED_AT,
    ];

    /**
     * @param  int[]|null  $attendee_ids
     * @param  int[]|null  $product_ids
     */
    public function __construct(
        public int $event_id,
        public bool $include_awaiting_payment,
        public ?array $attendee_ids = null,
        public ?int $order_id = null,
        public ?array $product_ids = null,
        public ?int $event_occurrence_id = null,
        public bool $only_unprinted = false,
        public string $sort_by = self::SORT_LAST_NAME,
        public int $page = 1,
        public int $per_page = 100,
    ) {}
}
