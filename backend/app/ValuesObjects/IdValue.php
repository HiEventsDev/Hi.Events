<?php

namespace TicketKitten\ValuesObjects;

use InvalidArgumentException;

class IdValue
{
    private int $value;

    public function __construct(int $id)
    {
        $this->setValue($id);
    }

    private function setValue(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException("Invalid ID provided. ID must be a positive integer.");
        }

        $this->value = $id;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function equals(IdValue $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return (string) $this->getValue();
    }
}
