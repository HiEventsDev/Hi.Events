<?php

declare(strict_types=1);

namespace HiEvents\Exceptions;

class InvalidWidgetSettingsException extends BaseException
{
    public function __construct(
        private readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getField(): string
    {
        return $this->field;
    }
}
