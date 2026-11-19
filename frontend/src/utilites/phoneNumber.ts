import {t} from "@lingui/macro";
import {parsePhoneNumberFromString} from "libphonenumber-js";

export const formatPhoneNumber = (value?: string | null): string => {
    if (!value) {
        return '';
    }
    return parsePhoneNumberFromString(value)?.formatInternational() ?? value;
};

const TEXT_MESSAGE_COUNTRIES = new Set<string>(['US', 'GB', 'IE', 'AU']);

export const isInTextMessageCountry = (value?: string | null): boolean => {
    const parsed = value ? parsePhoneNumberFromString(value) : undefined;

    return !!parsed?.country && parsed.isValid() && TEXT_MESSAGE_COUNTRIES.has(parsed.country);
};

const NORTH_AMERICAN_CALLING_CODE = '1';

export const getSmsConsentTerms = (value?: string | null): string => {
    const callingCode = value ? parsePhoneNumberFromString(value)?.countryCallingCode : undefined;

    return callingCode === NORTH_AMERICAN_CALLING_CODE
        ? t`Message frequency varies. Msg & data rates may apply. Reply STOP to opt out or HELP for help.`
        : t`You can opt out at any time.`;
};
