<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Exceptions;

use Exception;

class SeatMapRelabelRequiresConfirmationException extends Exception
{
    /**
     * @param  string[]  $seatLabels
     */
    public function __construct(private readonly array $seatLabels)
    {
        parent::__construct(__('This change renames seats that are already sold or held. Confirm to rename them on their tickets too.'));
    }

    /**
     * @return string[]
     */
    public function getSeatLabels(): array
    {
        return $this->seatLabels;
    }
}
