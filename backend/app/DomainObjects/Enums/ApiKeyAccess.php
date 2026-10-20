<?php

namespace HiEvents\DomainObjects\Enums;

enum ApiKeyAccess: string
{
    use BaseEnum;

    public const READ_ABILITY = 'read';

    public const WRITE_ABILITY = 'write';

    case READ_ONLY = 'READ_ONLY';
    case READ_WRITE = 'READ_WRITE';

    /**
     * @return string[]
     */
    public function abilities(): array
    {
        return match ($this) {
            self::READ_ONLY => [self::READ_ABILITY],
            self::READ_WRITE => [self::READ_ABILITY, self::WRITE_ABILITY],
        };
    }

    /**
     * @param  string[]  $abilities
     */
    public static function fromAbilities(array $abilities): self
    {
        return in_array(self::WRITE_ABILITY, $abilities, true) ? self::READ_WRITE : self::READ_ONLY;
    }
}
