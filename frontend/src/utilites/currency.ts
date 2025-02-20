export const formatCurrency = (value: number | string, currency = 'USD') => {
    const formatter = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
    });

    return formatter.format(value as number);
}

export const getCurrencySymbol = (currencyCode: string): string => {
    const currencySymbols: { [key: string]: string } = {
        'USD': '$',   // United States Dollar
        'CAD': '$',   // Canadian Dollar
        'EUR': '€',   // Euro
        'GBP': '£',   // British Pound
        'JPY': '¥',   // Japanese Yen
        'CNY': '¥',   // Chinese Yuan
        'INR': '₹',   // Indian Rupee
        'RUB': '₽',   // Russian Ruble
        'AUD': '$',   // Australian Dollar
        'CHF': 'CHF', // Swiss Franc
        'BRL': 'R$',  // Brazilian Real
        'ZAR': 'R',   // South African Rand
        'MXN': '$',   // Mexican Peso
        'KRW': '₩',   // South Korean Won
        'SGD': '$',   // Singapore Dollar
        'HKD': '$',   // Hong Kong Dollar
        'NOK': 'kr',  // Norwegian Krone
        'SEK': 'kr',  // Swedish Krona
        'NZD': '$',   // New Zealand Dollar
        'TRY': '₺',   // Turkish Lira
        'THB': '฿',   // Thai Baht
        'IDR': 'Rp',  // Indonesian Rupiah
        'PLN': 'zł',  // Polish Zloty
        'TWD': 'NT$', // New Taiwan Dollar
        'DKK': 'kr',  // Danish Krone
        'MYR': 'RM',  // Malaysian Ringgit
        'HUF': 'Ft',  // Hungarian Forint
        'CZK': 'Kč',  // Czech Koruna
        'AED': 'د.إ', // UAE Dirham
        'SAR': '﷼',   // Saudi Riyal
        'PHP': '₱',   // Philippine Peso
        'COP': '$',   // Colombian Peso
        'RON': 'lei', // Romanian Leu
        'ARS': '$',   // Argentine Peso
        'EGP': '£',   // Egyptian Pound
        'VND': '₫',   // Vietnamese Dong
        'BDT': '৳',   // Bangladeshi Taka
        'PKR': '₨',   // Pakistani Rupee
        'CLP': '$',   // Chilean Peso
        'ILS': '₪',   // Israeli Shekel
        'NGN': '₦',   // Nigerian Naira
        'PEN': 'S/',  // Peruvian Sol
        'UAH': '₴',   // Ukrainian Hryvnia
        'QAR': '﷼',   // Qatari Riyal
        'KZT': '₸',   // Kazakhstani Tenge
        'KES': 'KSh', // Kenyan Shilling
        'GHS': '₵',   // Ghanaian Cedi
        'DZD': 'د.ج', // Algerian Dinar
        'MAD': 'د.م.',// Moroccan Dirham
        'IQD': 'ع.د', // Iraqi Dinar
        'ISK': 'kr',  // Icelandic Króna
        'LKR': '₨',   // Sri Lankan Rupee
        'ETB': 'Br',  // Ethiopian Birr
        'BOB': 'Bs.', // Bolivian Boliviano
        'UYU': '$U',  // Uruguayan Peso
        'TND': 'د.ت', // Tunisian Dinar
        'JOD': 'د.ا', // Jordanian Dinar
        'OMR': '﷼',   // Omani Rial
        'BHD': '.د.ب', // Bahraini Dinar
        'KWD': 'د.ك', // Kuwaiti Dinar
        'LBP': 'ل.ل', // Lebanese Pound
        'XOF': 'CFA', // West African CFA franc
        'XAF': 'FCFA', // Central African CFA franc
        'XPF': '₣',   // CFP Franc
    };

    return currencySymbols[currencyCode] || '';
};

type CurrencyCode =
    | 'USD' | 'EUR' | 'GBP' | 'CAD' | 'AUD' | 'NZD' | 'CHF' | 'JPY' | 'CNY' | 'HKD'
    | 'SGD' | 'INR' | 'ZAR' | 'RUB' | 'BRL' | 'KRW' | 'TWD' | 'MXN' | 'ARS' | 'CLP'
    | 'THB' | 'TRY' | 'VND' | 'AED' | 'ILS' | 'PHP' | 'MYR' | 'PKR' | 'LKR' | 'BWP'
    | 'GHS' | 'KES' | 'NGN' | 'TZS' | 'UGX' | 'SEK' | 'DKK' | 'NOK' | 'ISK' | 'MOP'
    | 'BND' | 'KHR' | 'BDT' | 'MMK' | 'LAK' | 'IDR' | 'MNT' | 'NPR' | 'COP' | 'PEN'
    | 'VES' | 'BOB' | 'PYG' | 'UYU' | 'CRC' | 'DOP' | 'HNL' | 'NIO' | 'GTQ' | 'SAR'
    | 'QAR' | 'KWD' | 'BHD' | 'OMR' | 'EGP' | 'MAD' | 'DZD' | 'TND' | 'LYD' | 'JOD'
    | 'LBP' | 'IQD' | 'YER' | 'IRR' | 'BYN' | 'KZT' | 'UAH' | 'PLN' | 'CZK' | 'HUF'
    | 'RON' | 'BGN' | 'RSD' | 'AZN' | 'GEL' | 'AMD' | 'KGS' | 'TJS' | 'TMT' | 'UZS'
    | 'ETB' | 'RWF' | 'XOF';

type LocaleCurrencyMap = {
    [key: string]: CurrencyCode;
};

const currencyByLocale: LocaleCurrencyMap = {
    // English variants
    'en-US': 'USD',
    'en-GB': 'GBP',
    'en-CA': 'CAD',
    'en-AU': 'AUD',
    'en-NZ': 'NZD',
    'en-IE': 'EUR',
    'en-ZA': 'ZAR',
    'en-IN': 'INR',
    'en-HK': 'HKD',
    'en-SG': 'SGD',
    'en-AE': 'AED',
    'en-IL': 'ILS',
    'en-PH': 'PHP',
    'en-MY': 'MYR',
    'en-MT': 'EUR',
    'en-PK': 'PKR',
    'en-LK': 'LKR',
    'en-BW': 'BWP',
    'en-GH': 'GHS',
    'en-KE': 'KES',
    'en-NG': 'NGN',
    'en-TZ': 'TZS',
    'en-UG': 'UGX',

    // European
    'de': 'EUR',
    'fr': 'EUR',
    'it': 'EUR',
    'es': 'EUR',
    'pt': 'EUR',
    'nl': 'EUR',
    'el': 'EUR',
    'de-CH': 'CHF',
    'fr-CH': 'CHF',
    'it-CH': 'CHF',
    'de-AT': 'EUR',
    'fr-BE': 'EUR',
    'nl-BE': 'EUR',
    'de-LU': 'EUR',
    'fr-LU': 'EUR',
    'es-AD': 'EUR',
    'ca-AD': 'EUR',
    'fi': 'EUR',
    'sv-FI': 'EUR',
    'sv': 'SEK',
    'da': 'DKK',
    'nb': 'NOK',
    'nn': 'NOK',
    'fo': 'DKK',
    'is': 'ISK',

    // Asian
    'ja': 'JPY',
    'zh': 'CNY',
    'zh-CN': 'CNY',
    'zh-HK': 'HKD',
    'zh-TW': 'TWD',
    'zh-MO': 'MOP',
    'zh-SG': 'SGD',
    'ko': 'KRW',
    'th': 'THB',
    'vi': 'VND',
    'ms': 'MYR',
    'ms-SG': 'SGD',
    'ms-BN': 'BND',
    'fil': 'PHP',
    'km': 'KHR',
    'bn': 'BDT',
    'bn-IN': 'INR',
    'hi': 'INR',
    'ta': 'INR',
    'ta-SG': 'SGD',
    'ta-LK': 'LKR',
    'te': 'INR',
    'ml': 'INR',
    'ur': 'PKR',
    'si': 'LKR',
    'my': 'MMK',
    'lo': 'LAK',
    'id': 'IDR',
    'mn': 'MNT',
    'ne': 'NPR',

    // Americas
    'es-MX': 'MXN',
    'es-AR': 'ARS',
    'es-CL': 'CLP',
    'es-CO': 'COP',
    'es-PE': 'PEN',
    'es-EC': 'USD',
    'es-VE': 'VES',
    'es-BO': 'BOB',
    'es-PY': 'PYG',
    'es-UY': 'UYU',
    'es-CR': 'CRC',
    'es-PA': 'USD',
    'es-DO': 'DOP',
    'es-HN': 'HNL',
    'es-SV': 'USD',
    'es-NI': 'NIO',
    'es-GT': 'GTQ',
    'pt-BR': 'BRL',

    // Middle East & North Africa
    'ar': 'AED',
    'ar-SA': 'SAR',
    'ar-AE': 'AED',
    'ar-QA': 'QAR',
    'ar-KW': 'KWD',
    'ar-BH': 'BHD',
    'ar-OM': 'OMR',
    'ar-EG': 'EGP',
    'ar-MA': 'MAD',
    'ar-DZ': 'DZD',
    'ar-TN': 'TND',
    'ar-LY': 'LYD',
    'ar-JO': 'JOD',
    'ar-LB': 'LBP',
    'ar-IQ': 'IQD',
    'ar-YE': 'YER',
    'he': 'ILS',
    'fa': 'IRR',
    'tr': 'TRY',

    // Eastern Europe & Central Asia
    'ru': 'RUB',
    'ru-BY': 'BYN',
    'ru-KZ': 'KZT',
    'uk': 'UAH',
    'pl': 'PLN',
    'cs': 'CZK',
    'sk': 'EUR',
    'hu': 'HUF',
    'ro': 'RON',
    'bg': 'BGN',
    'hr': 'EUR',
    'sr': 'RSD',
    'sl': 'EUR',
    'et': 'EUR',
    'lv': 'EUR',
    'lt': 'EUR',
    'az': 'AZN',
    'ka': 'GEL',
    'hy': 'AMD',
    'kk': 'KZT',
    'ky': 'KGS',
    'tg': 'TJS',
    'tk': 'TMT',
    'uz': 'UZS',

    // Africa
    'af': 'ZAR',
    'am': 'ETB',
    'sw': 'KES',
    'sw-TZ': 'TZS',
    'sw-UG': 'UGX',
    'zu': 'ZAR',
    'xh': 'ZAR',
    'st': 'ZAR',
    'rw': 'RWF',
    'wo': 'XOF',
    'sn': 'XOF',
    'ha': 'NGN',
    'yo': 'NGN',
    'ig': 'NGN'
};

// Euro zone country codes
const euroZoneCountries = [
    'AT', 'BE', 'CY', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT',
    'LV', 'LT', 'LU', 'MT', 'NL', 'PT', 'SK', 'SI', 'ES', 'HR'
] as const;

export const getUserCurrency = (): CurrencyCode => {
    if (typeof window === 'undefined') return 'USD';

    try {
        const userLocales = [
            // Primary: Full user locale (e.g., 'en-US')
            navigator.language,
            // Secondary: Browser locales if available
            ...(navigator.languages || []),
            // Tertiary: Language-only portion of primary locale (e.g., 'en')
            navigator.language.split('-')[0]
        ];

        // Try each locale in order until we find a match
        for (const locale of userLocales) {
            const currency = currencyByLocale[locale];
            if (currency) {
                return currency;
            }
        }

        // If no direct match, try matching just the language part
        const languageOnly = navigator.language.split('-')[0];
        const languageCurrency = currencyByLocale[languageOnly];
        if (languageCurrency) {
            return languageCurrency;
        }

        // Check if it's a Euro country by region code
        const region = navigator.language.split('-')[1];
        if (region && euroZoneCountries.includes(region as typeof euroZoneCountries[number])) {
            return 'EUR';
        }

        // Default to USD if no match found
        return 'USD';
    } catch (error) {
        // Fallback to USD if anything goes wrong
        return 'USD';
    }
};
