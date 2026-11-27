import {t} from "@lingui/macro";
import {apiErrorStatus, firstApiError} from "../../../utilites/apiErrors.ts";

export const signupSubmissionError = (error: unknown): string => {
    const fallback = t`We couldn't sign you up. Please try again.`;

    switch (apiErrorStatus(error)) {
        case 422:
            return firstApiError(error, fallback);
        case 429:
            return t`Too many attempts from this connection. Please try again in a minute.`;
        case 404:
            return t`This form isn't available any more.`;
        default:
            return fallback;
    }
};
