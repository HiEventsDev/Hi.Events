<?php

namespace HiEvents\Services\Domain\Event\DTO;

use HiEvents\Services\Handlers\Event\DTO\CreateEventDTO as ApplicationCreateEventDTO;

/**
 * This will always be identical to the CreateEventDTO in the Handlers namespace. So we can just extend it.
 */
class CreateEventDTO extends ApplicationCreateEventDTO
{

}
