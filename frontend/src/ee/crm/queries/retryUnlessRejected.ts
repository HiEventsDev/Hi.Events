import {apiErrorStatus} from "../../../utilites/apiErrors.ts";

const RETRYABLE_CLIENT_STATUSES = [408, 429];

const isRejectedRequest = (error: unknown): boolean => {
    const status = apiErrorStatus(error) ?? 0;

    return status >= 400 && status < 500 && !RETRYABLE_CLIENT_STATUSES.includes(status);
};

export const retryUnlessRejected = (failureCount: number, error: unknown): boolean =>
    !isRejectedRequest(error) && failureCount < 3;
