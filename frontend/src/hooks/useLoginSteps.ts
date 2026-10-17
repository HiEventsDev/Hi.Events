import {useRef, useState} from "react";
import {useMutation} from "@tanstack/react-query";
import {t} from "@lingui/macro";
import {authClient} from "../api/auth.client.ts";
import {redirectToPreviousUrl} from "../api/client.ts";
import {Account, IdParam, LoginResponse, TwoFactorLoginRequest} from "../types.ts";

export type LoginStep = 'start' | 'two-factor' | 'choose-account' | 'recovery-notice';

type AccountChooser = (accountId: IdParam) => void;

interface UseLoginStepsOptions {
    ssoBinding?: string;
    onComplete?: () => void;
}

export const useLoginSteps = ({ssoBinding, onComplete = redirectToPreviousUrl}: UseLoginStepsOptions = {}) => {
    const [step, setStep] = useState<LoginStep>('start');
    const [error, setError] = useState<string | null>(null);
    const [twoFactorError, setTwoFactorError] = useState<string | null>(null);
    const [accounts, setAccounts] = useState<Account[]>([]);
    const [pendingAccountId, setPendingAccountId] = useState<IdParam | null>(null);
    const [recoveryCodesRemaining, setRecoveryCodesRemaining] = useState(0);
    const challengeTokenRef = useRef<string | null>(null);
    const chooserRef = useRef<AccountChooser | null>(null);

    const reset = (message?: string) => {
        challengeTokenRef.current = null;
        chooserRef.current = null;
        setPendingAccountId(null);
        setTwoFactorError(null);
        setStep('start');
        setError(message ?? null);
    };

    const verifyMutation = useMutation({
        mutationFn: (request: TwoFactorLoginRequest) => authClient.loginWithTwoFactor(request),
        onSuccess: (response: LoginResponse) => handleResponse(response, (accountId) => verifyMutation.mutate({
            challenge_token: challengeTokenRef.current as string,
            account_id: accountId,
            sso_binding: ssoBinding,
        })),
        onError: (requestError: any) => {
            const status = requestError?.response?.status;
            const data = requestError?.response?.data;
            setPendingAccountId(null);

            if (status === 401) {
                reset(data?.message ?? t`Your sign-in session has expired. Please sign in again.`);
                return;
            }

            if (status === 429) {
                setTwoFactorError(t`Too many attempts. Please wait a minute and try again.`);
                return;
            }

            setTwoFactorError(data?.errors?.code?.[0]
                ?? data?.errors?.recovery_code?.[0]
                ?? t`Something went wrong. Please try again.`);
        },
    });

    const startTwoFactor = (challengeToken: string) => {
        challengeTokenRef.current = challengeToken;
        setTwoFactorError(null);
        setStep('two-factor');
    };

    const showAccounts = (choices: Account[], chooser: AccountChooser) => {
        chooserRef.current = chooser;
        setAccounts(choices);
        setPendingAccountId(null);
        setStep('choose-account');
    };

    const handleResponse = (response: LoginResponse, chooser: AccountChooser) => {
        setError(null);

        if (response.two_factor_required && response.two_factor_challenge_token) {
            startTwoFactor(response.two_factor_challenge_token);
            return;
        }

        if (response.token) {
            if (response.two_factor_recovery_codes_remaining !== undefined) {
                setRecoveryCodesRemaining(response.two_factor_recovery_codes_remaining);
                setStep('recovery-notice');
                return;
            }

            onComplete();
            return;
        }

        if (response.accounts?.length) {
            showAccounts(response.accounts, chooser);
            return;
        }

        reset(t`Your account isn't active. Ask the account owner to invite you again.`);
    };

    const chooseAccount = (accountId: IdParam) => {
        setPendingAccountId(accountId);
        chooserRef.current?.(accountId);
    };

    const verifyTwoFactor = (payload: Omit<TwoFactorLoginRequest, 'challenge_token' | 'sso_binding'>) => {
        if (!challengeTokenRef.current) {
            return;
        }

        verifyMutation.mutate({
            challenge_token: challengeTokenRef.current,
            sso_binding: ssoBinding,
            ...payload,
        });
    };

    return {
        step,
        error,
        setError,
        accounts,
        pendingAccountId,
        setPendingAccountId,
        recoveryCodesRemaining,
        twoFactorError,
        setTwoFactorError,
        isVerifying: verifyMutation.isPending,
        handleResponse,
        startTwoFactor,
        showAccounts,
        chooseAccount,
        verifyTwoFactor,
        reset,
        complete: onComplete,
    };
};

export type LoginSteps = ReturnType<typeof useLoginSteps>;
