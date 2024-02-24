<?php

namespace HiEvents\Http\DataTransferObjects;

use Illuminate\Support\Collection;
use HiEvents\DataTransferObjects\BaseDTO;

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
