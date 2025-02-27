<?php

namespace HiEvents\Services\Application\Handlers\Product\DTO;

use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\Services\Domain\Product\DTO\ProductPriceDTO;
use Illuminate\Support\Collection;

class UpsertProductDTO extends BaseDTO
{
    public function __construct(
        public readonly int              $account_id,
        public readonly int              $event_id,
        public readonly int              $product_category_id,
        public readonly string           $title,
        public readonly ProductPriceType $type,
        public readonly ProductType      $product_type,
        #[CollectionOf(ProductPriceDTO::class)]
        public readonly ?Collection      $prices = null,
        public readonly ?float           $price = 0.00,
        public readonly ?int             $order = 1,
        public readonly ?int             $initial_quantity_available = null,
        public readonly ?int             $quantity_sold = 0,
        public readonly ?string          $sale_start_date = null,
        public readonly ?string          $sale_end_date = null,
        public readonly ?int             $max_per_order = 100,
        public readonly ?string          $description = null,
        public readonly ?int             $min_per_order = 0,
        public readonly ?bool            $is_hidden = false,
        public readonly ?bool            $hide_before_sale_start_date = false,
        public readonly ?bool            $hide_after_sale_end_date = false,
        public readonly ?bool            $hide_when_sold_out = false,
        public readonly ?bool            $start_collapsed = false,
        public readonly ?bool            $show_quantity_remaining = false,
        public readonly ?bool            $is_hidden_without_promo_code = false,
        public readonly ?array           $tax_and_fee_ids = [],
        public readonly ?int             $product_id = null,
    )
    {
    }
}

