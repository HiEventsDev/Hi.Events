<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;
use Illuminate\Support\Collection;

class CreateManualOrderDTO extends BaseDTO
{
    public function __construct(
        public readonly int        $event_id,
        public readonly string     $first_name,
        public readonly string     $last_name,
        public readonly string     $email,
        public readonly string     $locale,
        public readonly bool       $send_confirmation_email = true,
        #[CollectionOf(ProductOrderDetailsDTO::class)]
        public readonly Collection $products = new \Illuminate\Support\Collection(),
        public readonly ?string    $promo_code = null,
        public readonly ?string    $notes = null,
    )
    {
    }
}
