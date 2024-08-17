<?php

namespace HiEvents\DataTransferObjects;

class ErrorBagDTO extends BaseDTO
{
    public function __construct(
        /**
         * @var array<string, string>
         */
        public array $errors = [],
    )
    {
    }

    public function addError(string $key, string $message): void
    {
        $this->errors[$key] = $message;
    }

    public function toArray(array $without = []): array
    {
        return $this->errors;
    }
}
