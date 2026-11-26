import {t} from "@lingui/macro";
import {CrmSmsCountry} from "../types.ts";

export const SMS_COUNTRIES: CrmSmsCountry[] = ['GB', 'IE', 'US', 'AU'];

export const smsCountryLabel = (country: CrmSmsCountry): string => ({
    US: t`United States`,
    GB: t`United Kingdom`,
    IE: t`Ireland`,
    AU: t`Australia`,
})[country];

export const smsOptsOutByReply = (country: CrmSmsCountry): boolean => country === 'US';

export const smsCountryList = (countries: CrmSmsCountry[]): string => countries.map(smsCountryLabel).join(', ');

export const smsCountryRequirement = (country: CrmSmsCountry): string => ({
    US: t`Needs a registered number (10DLC or toll-free). Texts end with "Reply STOP to opt out."`,
    GB: t`Sender names work without registration. Texts end with an opt-out link.`,
    IE: t`Register your sender name with ComReg, or texts show as "Likely Scam". Texts end with an opt-out link.`,
    AU: t`Register your sender name with the ACMA (needs an ABN), or texts show as "Unverified". Texts end with an opt-out link.`,
})[country];
