<?php

namespace HiEvents\Enterprise\Seating\Exceptions;

use Exception;

class SeatsUnavailableException extends Exception
{
    /**
     * @param  string[]  $seatUids
     */
    public function __construct(private readonly array $seatUids)
    {
        parent::__construct(__('Some of the seats you selected are no longer available'));
    }

    /**
     * @return string[]
     */
    public function getSeatUids(): array
    {
        return $this->seatUids;
    }
}
