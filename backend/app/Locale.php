<?php

namespace HiEvents;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum Locale: string
{
    use BaseEnum;

    case EN = 'en';
    case DE = 'de';
    case FR = 'fr';
    case IT = 'it';
    case NL = 'nl';
    case HU = 'hu';
    case ES = 'es';
    case PT = 'pt';
    case PT_BR = 'pt-br';
    case ZH_CN = 'zh-cn';

    case ZH_HK = 'zh-hk';
    case VI = 'vi';

    case TR = 'tr';

    public static function getSupportedLocales(): array
    {
        return self::valuesArray();
    }
}
