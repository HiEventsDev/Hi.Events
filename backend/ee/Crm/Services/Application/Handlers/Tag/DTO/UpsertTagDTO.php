<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Tag\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertTagDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly string $name,
        public readonly ?string $color,
        public readonly ?int $tag_id = null,
    ) {}
}
