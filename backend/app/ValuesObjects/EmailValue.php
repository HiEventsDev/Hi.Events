<?php

namespace HiEvents\ValuesObjects;

use InvalidArgumentException;

class EmailValue
{
    private string $value;

    public function __construct(string $email)
    {
        $this->setValue($email);
    }

    private function setValue(string $email): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid email address provided.");
        }

        $this->value = $email;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(EmailValue $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->getValue();
    }
}
