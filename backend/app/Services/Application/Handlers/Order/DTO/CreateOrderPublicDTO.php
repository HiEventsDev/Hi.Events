<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use Illuminate\Support\Collection;

class CreateOrderPublicDTO extends BaseDTO
{
    public function __construct(
        /**
         * @var Collection<ProductOrderDetailsDTO>
         */
        public readonly Collection $products,
        public readonly bool       $is_user_authenticated,
        public readonly string     $session_identifier,
        public readonly ?string    $order_locale = null,
        public readonly ?string    $promo_code = null,
        public readonly ?string    $affiliate_code = null,
    )
    {
    }
}
