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
