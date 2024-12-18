<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<ProductPriceDomainObject>
 */
interface ProductPriceRepositoryInterface extends RepositoryInterface
{
}
