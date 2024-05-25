<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<StripePaymentDomainObject>
 */
interface StripePaymentsRepositoryInterface extends RepositoryInterface
{
}
