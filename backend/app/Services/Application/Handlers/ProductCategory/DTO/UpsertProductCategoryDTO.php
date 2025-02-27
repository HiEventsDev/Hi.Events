<?php

namespace HiEvents\Services\Application\Handlers\ProductCategory\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class UpsertProductCategoryDTO extends BaseDTO
{
    public function __construct(
        public string  $name,
        public ?string $description,
        public bool    $is_hidden,
        public int     $event_id,
        public ?string $no_products_message = '',
        public ?int    $product_category_id = null,
    )
    {
    }
}
