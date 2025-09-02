<?php

namespace HiEvents\DomainObjects\Enums;

enum StripePlatform: string
{
    case CANADA = 'ca';
    case IRELAND = 'ie';
    
    public static function fromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }
        
        return self::tryFrom($value);
    }
    
    public function toString(): string
    {
        return $this->value;
    }
    
    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}