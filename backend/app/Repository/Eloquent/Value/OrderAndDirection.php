<?php

namespace HiEvents\Repository\Eloquent\Value;

use InvalidArgumentException;

class OrderAndDirection
{
    public const DIRECTION_ASC = 'asc';
    public const DIRECTION_DESC = 'desc';

    public function __construct(
        private readonly string $order,
        private readonly string $direction = self::DIRECTION_ASC,
    )
    {
        $this->validate();
    }

    public function getOrder(): string
    {
        return $this->order;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    private function validate(): void
    {
        if (!in_array($this->direction, ['asc', 'desc'])) {
            throw new InvalidArgumentException(__('Invalid direction. Must be either asc or desc'));
        }
    }
}
