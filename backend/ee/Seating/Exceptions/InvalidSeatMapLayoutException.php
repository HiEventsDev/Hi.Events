<?php

namespace HiEvents\Enterprise\Seating\Exceptions;

use Exception;

class InvalidSeatMapLayoutException extends Exception
{
    /**
     * @param  array<string, string>  $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(__('The seat map layout is invalid'));
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
