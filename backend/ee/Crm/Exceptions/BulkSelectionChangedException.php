<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Exceptions;

use HiEvents\Exceptions\ResourceConflictException;

class BulkSelectionChangedException extends ResourceConflictException
{
    public function __construct(public readonly int $total)
    {
        parent::__construct(trans_choice(
            '{1} The selection changed to :count contact. Confirm again to continue.|[2,*] The selection changed to :count contacts. Confirm again to continue.',
            $total,
        ));
    }
}
