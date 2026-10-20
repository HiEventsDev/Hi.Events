<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\DTO;

use Carbon\CarbonImmutable;
use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Repository\DTO\SalesByCurrencyDTO;
use Illuminate\Support\Collection;

class LicenceSalesPeriodDTO extends BaseDataObject
{
    /**
     * @param  Collection<SalesByCurrencyDTO>  $sales
     */
    public function __construct(
        public readonly CarbonImmutable $starts_at,
        public readonly CarbonImmutable $ends_at,
        public readonly bool $is_current,
        public readonly Collection $sales,
    ) {}
}
