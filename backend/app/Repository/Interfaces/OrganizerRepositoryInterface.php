<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<OrganizerDomainObject>
 */
interface OrganizerRepositoryInterface extends RepositoryInterface
{
}
