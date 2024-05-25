<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<AccountUserDomainObject>
 */
interface AccountUserRepositoryInterface extends RepositoryInterface
{
}
