import {formatAddress} from "./addressUtilities.ts";

export const isAddress = (obj: any) => {
    if (!obj || typeof obj !== 'object') return false;

    const addressFields = [
        'address_line_1',
        'address_line_2',
        'city',
        'state_or_region',
        'zip_or_postal_code',
        'country'
    ];

    return addressFields.some(field => field in obj);
};

export const formatAnswer = (answer: any) => {
    if (answer === null || answer === undefined) return '';

    if (Array.isArray(answer)) {
        return answer.join(", ");
    } else if (typeof answer === 'object') {
        if (isAddress(answer)) {
            return formatAddress(answer);
        } else {
            return JSON.stringify(answer);
        }
    } else {
        return answer.toString();
    }
};
