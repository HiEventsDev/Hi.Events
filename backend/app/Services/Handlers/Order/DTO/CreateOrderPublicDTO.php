<?php

namespace HiEvents\Services\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use Illuminate\Support\Collection;

class CreateOrderPublicDTO extends BaseDTO
{
    public function __construct(
        /**
         * @var Collection<TicketOrderDetailsDTO>
         */
        public readonly Collection $tickets,
        public readonly bool       $is_user_authenticated,
        public readonly ?string    $promo_code = null,
    )
    {
    }
}
