<?php

namespace HiEvents\DomainObjects\Enums;

trait BaseEnum
{
    public function getName(): string
    {
        return $this->name;
    }

    public static function fromName(string $name): static
    {
        return constant("self::$name");
    }

    public static function valuesArray(): array
    {
        $values = [];

        foreach (self::cases() as $enum) {
            $values[] = $enum->value ?? $enum->name;
        }

        return $values;
    }

    public static function valuesCsv(string $separator = ','): string
    {
        return implode($separator, self::valuesArray());
    }
}
