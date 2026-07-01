import {useMemo} from "react";
import {useSearchParams} from "react-router";

export interface CheckoutPrefill {
    first_name?: string;
    last_name?: string;
    email?: string;
}

export interface UseCheckoutPrefillResult {
    prefill: CheckoutPrefill;
    lock: boolean;
}

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export const useCheckoutPrefill = (): UseCheckoutPrefillResult => {
    const [searchParams] = useSearchParams();

    const firstNameParam = searchParams.get("first_name");
    const lastNameParam = searchParams.get("last_name");
    const emailParam = searchParams.get("email");
    const lockParam = searchParams.get("lock");

    return useMemo(() => {
        const prefill: CheckoutPrefill = {};

        const firstName = firstNameParam?.trim();
        if (firstName) {
            prefill.first_name = firstName;
        }

        const lastName = lastNameParam?.trim();
        if (lastName) {
            prefill.last_name = lastName;
        }

        const email = emailParam?.trim();
        if (email && EMAIL_REGEX.test(email)) {
            prefill.email = email;
        }

        const hasPrefill = Object.keys(prefill).length > 0;
        const lock = hasPrefill && (lockParam === "true" || lockParam === "1");

        return {prefill, lock};
    }, [firstNameParam, lastNameParam, emailParam, lockParam]);
};
