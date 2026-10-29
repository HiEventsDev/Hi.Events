import {t} from "@lingui/macro";
import {SsoErrorResponse} from "../types.ts";

export const ssoError = (error: unknown): SsoErrorResponse => {
    const data = (error as { response?: { data?: Partial<SsoErrorResponse> } })?.response?.data;
    const status = (error as { response?: { status?: number } })?.response?.status;

    if ((error as { code?: string })?.code === 'ECONNABORTED') {
        return {message: t`The sign-in service isn't responding.`, error_code: 'SSO_PROVIDER_UNAVAILABLE'};
    }

    if (status === 429) {
        return {message: t`Too many attempts. Please wait a minute and try again.`, error_code: 'RATE_LIMITED'};
    }

    return {
        message: data?.message || t`Something went wrong. Please try again.`,
        error_code: data?.error_code || 'UNKNOWN',
        context: data?.context,
    };
};
