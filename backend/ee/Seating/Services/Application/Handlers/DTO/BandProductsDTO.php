<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO;

use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDataObject;
use Illuminate\Support\Collection;

class BandProductsDTO extends BaseDataObject
{
    /**
     * @param  Collection<int, BandProductLinkDTO>  $products
     */
    public function __construct(
        public readonly string $band_key,
        #[CollectionOf(BandProductLinkDTO::class)]
        public readonly Collection $products,
    ) {}
}
