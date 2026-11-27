<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class EarlyAccessGrantDTO extends BaseDataObject
{
    /**
     * @param  int[]  $hidden_product_ids
     */
    public function __construct(
        public readonly int $promo_code_id,
        public readonly array $hidden_product_ids,
    ) {}
}
