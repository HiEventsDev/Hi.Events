<?php

namespace HiEvents\DomainObjects\Enums;

enum EmailTemplateEngine: string
{
    use BaseEnum;

    case LIQUID = 'liquid';
    case BLADE = 'blade'; // For future use

    public function label(): string
    {
        return match ($this) {
            self::LIQUID => __('Liquid'),
            self::BLADE => __('Blade'),
        };
    }
}