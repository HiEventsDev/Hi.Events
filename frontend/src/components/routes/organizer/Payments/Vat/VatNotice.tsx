const EU_COUNTRIES = [
    'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
    'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
    'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
];

export const getVatInfo = (stripeCountry?: string | null) => {
    if (!stripeCountry || !EU_COUNTRIES.includes(stripeCountry.toUpperCase())) {
        return {
            isEU: false,
            isIreland: false,
            showVatForm: false,
        };
    }

    const isIreland = stripeCountry.toUpperCase() === 'IE';

    return {
        isEU: true,
        isIreland,
        showVatForm: !isIreland,
    };
};
