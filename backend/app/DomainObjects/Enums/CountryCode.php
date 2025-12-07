<?php

namespace HiEvents\DomainObjects\Enums;

enum CountryCode: string
{
    use BaseEnum;

    // 🌍 Africa
    case DZ = 'DZ'; // Algeria
    case AO = 'AO'; // Angola
    case BJ = 'BJ'; // Benin
    case BW = 'BW'; // Botswana
    case BF = 'BF'; // Burkina Faso
    case BI = 'BI'; // Burundi
    case CV = 'CV'; // Cabo Verde
    case CM = 'CM'; // Cameroon
    case CF = 'CF'; // Central African Republic
    case TD = 'TD'; // Chad
    case KM = 'KM'; // Comoros
    case CD = 'CD'; // Congo (DRC)
    case CG = 'CG'; // Congo (Republic)
    case CI = 'CI'; // Côte d'Ivoire
    case DJ = 'DJ'; // Djibouti
    case EG = 'EG'; // Egypt
    case GQ = 'GQ'; // Equatorial Guinea
    case ER = 'ER'; // Eritrea
    case SZ = 'SZ'; // Eswatini
    case ET = 'ET'; // Ethiopia
    case GA = 'GA'; // Gabon
    case GM = 'GM'; // Gambia
    case GH = 'GH'; // Ghana
    case GN = 'GN'; // Guinea
    case GW = 'GW'; // Guinea-Bissau
    case KE = 'KE'; // Kenya
    case LS = 'LS'; // Lesotho
    case LR = 'LR'; // Liberia
    case LY = 'LY'; // Libya
    case MG = 'MG'; // Madagascar
    case MW = 'MW'; // Malawi
    case ML = 'ML'; // Mali
    case MR = 'MR'; // Mauritania
    case MU = 'MU'; // Mauritius
    case MA = 'MA'; // Morocco
    case MZ = 'MZ'; // Mozambique
    case NA = 'NA'; // Namibia
    case NE = 'NE'; // Niger
    case NG = 'NG'; // Nigeria
    case RE = 'RE'; // Réunion
    case RW = 'RW'; // Rwanda
    case ST = 'ST'; // São Tomé and Príncipe
    case SN = 'SN'; // Senegal
    case SC = 'SC'; // Seychelles
    case SL = 'SL'; // Sierra Leone
    case SO = 'SO'; // Somalia
    case ZA = 'ZA'; // South Africa
    case SS = 'SS'; // South Sudan
    case SD = 'SD'; // Sudan
    case TZ = 'TZ'; // Tanzania
    case TG = 'TG'; // Togo
    case TN = 'TN'; // Tunisia
    case UG = 'UG'; // Uganda
    case EH = 'EH'; // Western Sahara
    case ZM = 'ZM'; // Zambia
    case ZW = 'ZW'; // Zimbabwe

    // 🌎 Americas
    case AI = 'AI'; // Anguilla
    case AG = 'AG'; // Antigua and Barbuda
    case AR = 'AR'; // Argentina
    case AW = 'AW'; // Aruba
    case BS = 'BS'; // Bahamas
    case BB = 'BB'; // Barbados
    case BZ = 'BZ'; // Belize
    case BM = 'BM'; // Bermuda
    case BO = 'BO'; // Bolivia
    case BQ = 'BQ'; // Bonaire, Sint Eustatius and Saba
    case BR = 'BR'; // Brazil
    case VG = 'VG'; // British Virgin Islands
    case CA = 'CA'; // Canada
    case KY = 'KY'; // Cayman Islands
    case CL = 'CL'; // Chile
    case CO = 'CO'; // Colombia
    case CR = 'CR'; // Costa Rica
    case CU = 'CU'; // Cuba
    case CW = 'CW'; // Curaçao
    case DM = 'DM'; // Dominica
    case DO = 'DO'; // Dominican Republic
    case EC = 'EC'; // Ecuador
    case SV = 'SV'; // El Salvador
    case FK = 'FK'; // Falkland Islands
    case GF = 'GF'; // French Guiana
    case GL = 'GL'; // Greenland
    case GD = 'GD'; // Grenada
    case GP = 'GP'; // Guadeloupe
    case GT = 'GT'; // Guatemala
    case GY = 'GY'; // Guyana
    case HT = 'HT'; // Haiti
    case HN = 'HN'; // Honduras
    case JM = 'JM'; // Jamaica
    case MQ = 'MQ'; // Martinique
    case MX = 'MX'; // Mexico
    case MS = 'MS'; // Montserrat
    case NI = 'NI'; // Nicaragua
    case PA = 'PA'; // Panama
    case PY = 'PY'; // Paraguay
    case PE = 'PE'; // Peru
    case PR = 'PR'; // Puerto Rico
    case BL = 'BL'; // Saint Barthélemy
    case KN = 'KN'; // Saint Kitts and Nevis
    case LC = 'LC'; // Saint Lucia
    case MF = 'MF'; // Saint Martin (French part)
    case PM = 'PM'; // Saint Pierre and Miquelon
    case VC = 'VC'; // Saint Vincent and the Grenadines
    case SX = 'SX'; // Sint Maarten (Dutch part)
    case SR = 'SR'; // Suriname
    case TT = 'TT'; // Trinidad and Tobago
    case TC = 'TC'; // Turks and Caicos Islands
    case US = 'US'; // United States
    case VI = 'VI'; // US Virgin Islands
    case UY = 'UY'; // Uruguay
    case VE = 'VE'; // Venezuela

    // 🌏 Asia
    case AF = 'AF'; // Afghanistan
    case AM = 'AM'; // Armenia
    case AZ = 'AZ'; // Azerbaijan
    case BH = 'BH'; // Bahrain
    case BD = 'BD'; // Bangladesh
    case BT = 'BT'; // Bhutan
    case BN = 'BN'; // Brunei
    case KH = 'KH'; // Cambodia
    case CN = 'CN'; // China
    case CY = 'CY'; // Cyprus
    case GE = 'GE'; // Georgia
    case HK = 'HK'; // Hong Kong
    case IN = 'IN'; // India
    case ID = 'ID'; // Indonesia
    case IR = 'IR'; // Iran
    case IQ = 'IQ'; // Iraq
    case IL = 'IL'; // Israel
    case JP = 'JP'; // Japan
    case JO = 'JO'; // Jordan
    case KZ = 'KZ'; // Kazakhstan
    case KW = 'KW'; // Kuwait
    case KG = 'KG'; // Kyrgyzstan
    case LA = 'LA'; // Laos
    case LB = 'LB'; // Lebanon
    case MO = 'MO'; // Macao
    case MY = 'MY'; // Malaysia
    case MV = 'MV'; // Maldives
    case MN = 'MN'; // Mongolia
    case MM = 'MM'; // Myanmar
    case NP = 'NP'; // Nepal
    case KP = 'KP'; // North Korea
    case OM = 'OM'; // Oman
    case PK = 'PK'; // Pakistan
    case PS = 'PS'; // Palestine
    case PH = 'PH'; // Philippines
    case QA = 'QA'; // Qatar
    case SA = 'SA'; // Saudi Arabia
    case SG = 'SG'; // Singapore
    case KR = 'KR'; // South Korea
    case LK = 'LK'; // Sri Lanka
    case SY = 'SY'; // Syria
    case TW = 'TW'; // Taiwan
    case TJ = 'TJ'; // Tajikistan
    case TH = 'TH'; // Thailand
    case TL = 'TL'; // Timor-Leste
    case TR = 'TR'; // Türkiye
    case TM = 'TM'; // Turkmenistan
    case AE = 'AE'; // UAE
    case UZ = 'UZ'; // Uzbekistan
    case VN = 'VN'; // Vietnam
    case YE = 'YE'; // Yemen

    // 🇪🇺 Europe
    case AX = 'AX'; // Åland Islands
    case AL = 'AL'; // Albania
    case AD = 'AD'; // Andorra
    case AT = 'AT'; // Austria
    case BY = 'BY'; // Belarus
    case BE = 'BE'; // Belgium
    case BA = 'BA'; // Bosnia and Herzegovina
    case BG = 'BG'; // Bulgaria
    case HR = 'HR'; // Croatia
    case CZ = 'CZ'; // Czechia
    case DK = 'DK'; // Denmark
    case EE = 'EE'; // Estonia
    case FO = 'FO'; // Faroe Islands
    case FI = 'FI'; // Finland
    case FR = 'FR'; // France
    case DE = 'DE'; // Germany
    case GI = 'GI'; // Gibraltar
    case GR = 'GR'; // Greece
    case GG = 'GG'; // Guernsey
    case HU = 'HU'; // Hungary
    case IS = 'IS'; // Iceland
    case IE = 'IE'; // Ireland
    case IM = 'IM'; // Isle of Man
    case IT = 'IT'; // Italy
    case JE = 'JE'; // Jersey
    case LV = 'LV'; // Latvia
    case LI = 'LI'; // Liechtenstein
    case LT = 'LT'; // Lithuania
    case LU = 'LU'; // Luxembourg
    case MT = 'MT'; // Malta
    case MD = 'MD'; // Moldova
    case MC = 'MC'; // Monaco
    case ME = 'ME'; // Montenegro
    case NL = 'NL'; // Netherlands
    case MK = 'MK'; // North Macedonia
    case NO = 'NO'; // Norway
    case PL = 'PL'; // Poland
    case PT = 'PT'; // Portugal
    case RO = 'RO'; // Romania
    case RU = 'RU'; // Russia
    case SM = 'SM'; // San Marino
    case RS = 'RS'; // Serbia
    case SK = 'SK'; // Slovakia
    case SI = 'SI'; // Slovenia
    case ES = 'ES'; // Spain
    case SJ = 'SJ'; // Svalbard and Jan Mayen
    case SE = 'SE'; // Sweden
    case CH = 'CH'; // Switzerland
    case UA = 'UA'; // Ukraine
    case GB = 'GB'; // United Kingdom
    case VA = 'VA'; // Vatican City

    // 🌊 Oceania
    case AS = 'AS'; // American Samoa
    case AU = 'AU'; // Australia
    case CK = 'CK'; // Cook Islands
    case FJ = 'FJ'; // Fiji
    case PF = 'PF'; // French Polynesia
    case GU = 'GU'; // Guam
    case KI = 'KI'; // Kiribati
    case MH = 'MH'; // Marshall Islands
    case FM = 'FM'; // Micronesia
    case NR = 'NR'; // Nauru
    case NC = 'NC'; // New Caledonia
    case NZ = 'NZ'; // New Zealand
    case NU = 'NU'; // Niue
    case NF = 'NF'; // Norfolk Island
    case MP = 'MP'; // Northern Mariana Islands
    case PW = 'PW'; // Palau
    case PG = 'PG'; // Papua New Guinea
    case PN = 'PN'; // Pitcairn Islands
    case WS = 'WS'; // Samoa
    case SB = 'SB'; // Solomon Islands
    case TK = 'TK'; // Tokelau
    case TO = 'TO'; // Tonga
    case TV = 'TV'; // Tuvalu
    case VU = 'VU'; // Vanuatu
    case WF = 'WF'; // Wallis and Futuna

    // 🌐 Other Territories
    case AQ = 'AQ'; // Antarctica
    case BV = 'BV'; // Bouvet Island
    case IO = 'IO'; // British Indian Ocean Territory
    case CX = 'CX'; // Christmas Island
    case CC = 'CC'; // Cocos (Keeling) Islands
    case TF = 'TF'; // French Southern Territories
    case GS = 'GS'; // South Georgia and the South Sandwich Islands
    case HM = 'HM'; // Heard Island and McDonald Islands
    case UM = 'UM'; // United States Minor Outlying Islands

    public static function isEuCountry(self $countryCode): bool
    {
        return in_array($countryCode, [
            self::AT,
            self::BE,
            self::BG,
            self::HR,
            self::CY,
            self::CZ,
            self::DK,
            self::EE,
            self::FI,
            self::FR,
            self::DE,
            self::GR,
            self::HU,
            self::IE,
            self::IT,
            self::LV,
            self::LT,
            self::LU,
            self::MT,
            self::NL,
            self::PL,
            self::PT,
            self::RO,
            self::SK,
            self::SI,
            self::ES,
            self::SE,
        ], true);
    }
}
