    export const formatCurrency = (value: number | string, currency = 'USD') => {
        const formatter =  new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2,
        });

        return formatter.format(value as number);
    }