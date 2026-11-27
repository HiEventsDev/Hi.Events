<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Exceptions;

use Exception;

class InvalidSegmentDefinitionException extends Exception
{
    /**
     * @param  array<string, string>  $errors
     */
    public function __construct(
        private readonly array $errors,
    ) {
        parent::__construct(__('The segment rules are not valid.'));
    }

    public static function textSubscribersByEmail(): self
    {
        return new self(['preset' => __('Text subscribers can only be sent text messages. Choose another audience for an email.')]);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
