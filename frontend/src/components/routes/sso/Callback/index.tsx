import {Loader} from "@mantine/core";
import {t} from "@lingui/macro";
import {useEffect, useRef, useState} from "react";
import {useNavigate} from "react-router";
import {redirectToPreviousUrl, SECURITY_SETTINGS_PATH, setPreviousUrl} from "../../../../api/client.ts";
import {userClient} from "../../../../api/user.client.ts";
import {useLoginSteps} from "../../../../hooks/useLoginSteps.ts";
import {useCompleteSsoLogin, useExchangeSsoCode, useStartSso} from "../../../../mutations/useSsoMutations.ts";
import {useGetSsoProviders} from "../../../../queries/useGetSsoProviders.ts";
import {IdParam, SsoErrorResponse, SsoExchangeResponse, SsoSignupDetails, SsoTestResult} from "../../../../types.ts";
import {clearStoredUtmData} from "../../../../utilites/utm.ts";
import {
    clearSsoFlow,
    getSsoFlow,
    isAccountConnection,
    markSsoSignupConversion,
    rememberConnectionSession,
    rememberLastSsoProvider,
    SSO_TEST_MESSAGE,
    SsoFlow,
    storeReauthProof
} from "../../../../utilites/ssoFlow.ts";
import {ssoError} from "../../../../utilites/ssoErrors.ts";
import {AuthShell} from "../../../layouts/AuthLayout/AuthShell.tsx";
import {LoginStepView} from "../../auth/Login/LoginStepView.tsx";
import {CompleteSignup} from "./CompleteSignup.tsx";
import {SsoErrorAction, SsoErrorState} from "./SsoErrorState.tsx";
import classes from "./SsoCallback.module.scss";

const SLOW_EXCHANGE_MS = 8000;
const SILENT_SIGN_IN_ERRORS = ['login_required', 'interaction_required', 'consent_required', 'account_selection_required'];

type View =
    | { kind: 'exchanging' }
    | { kind: 'steps' }
    | { kind: 'signup', signup: SsoSignupDetails, providerName: string }
    | { kind: 'error', error: SsoErrorResponse };

const isSelfService = (flow: SsoFlow | null) => flow?.intent === 'LINK' || flow?.intent === 'REAUTH' || flow?.intent === 'TEST';

type TestOutcome = { test: SsoTestResult } | { error: string };

const postToOpener = (outcome: TestOutcome): boolean => {
    if (!window.opener || window.opener === window) {
        return false;
    }

    try {
        window.opener.postMessage({type: SSO_TEST_MESSAGE, ...outcome}, window.location.origin);
        return true;
    } catch {
        return false;
    }
};

const SsoCallback = () => {
    const navigate = useNavigate();
    const startedRef = useRef(false);
    const stateRef = useRef<string | null>(null);
    const returnPathRef = useRef<string | null>(null);
    const [flow, setFlow] = useState<SsoFlow | null>(null);
    const [view, setView] = useState<View>({kind: 'exchanging'});
    const [isSlow, setIsSlow] = useState(false);
    const {data: sso} = useGetSsoProviders();
    const passwordLoginEnabled = sso?.password_login_enabled ?? true;
    const exchangeMutation = useExchangeSsoCode();
    const completeLoginMutation = useCompleteSsoLogin();
    const startSso = useStartSso();

    const finishLogin = () => {
        clearSsoFlow(stateRef.current);

        if (returnPathRef.current) {
            setPreviousUrl(returnPathRef.current);
        }

        redirectToPreviousUrl(true);
    };

    const fail = (error: SsoErrorResponse) => setView({kind: 'error', error});

    const steps = useLoginSteps({ssoBinding: flow?.binding, onComplete: finishLogin, onSsoRequired: fail});

    const finishTest = (outcome: TestOutcome, currentFlow: SsoFlow | null) => {
        clearSsoFlow(stateRef.current);

        if (postToOpener(outcome)) {
            window.close();
            return;
        }

        navigate(currentFlow?.returnPath ?? '/settings/account', {replace: true, state: {ssoTest: outcome}});
    };

    const chooseAccountWithTicket = (loginTicket: string, binding: string) => {
        const chooser = (accountId: IdParam) => {
            completeLoginMutation.mutate({login_ticket: loginTicket, binding, account_id: accountId}, {
                onSuccess: response => steps.handleResponse(response, chooser),
                onError: error => fail(ssoError(error)),
            });
        };

        return chooser;
    };

    const handleExchange = (response: SsoExchangeResponse, currentFlow: SsoFlow) => {
        returnPathRef.current = response.return_path;

        if (!isSelfService(currentFlow)) {
            rememberLastSsoProvider(currentFlow.provider);
        }

        if (isAccountConnection(currentFlow.provider) && currentFlow.intent === 'LOGIN') {
            rememberConnectionSession(currentFlow.provider);
        }

        switch (response.status) {
            case 'TESTED':
                finishTest({test: response.test as SsoTestResult}, currentFlow);
                return;
            case 'AUTHENTICATED':
                finishLogin();
                return;
            case 'TWO_FACTOR_REQUIRED':
                setView({kind: 'steps'});
                steps.startTwoFactor(response.two_factor_challenge_token as string);
                return;
            case 'CHOOSE_ACCOUNT':
                setView({kind: 'steps'});
                steps.showAccounts(response.accounts ?? [], chooseAccountWithTicket(response.login_ticket as string, currentFlow.binding));
                return;
            case 'SIGNUP_REQUIRED':
                setView({kind: 'signup', signup: response.signup as SsoSignupDetails, providerName: response.provider_name});
                return;
            case 'LINKED':
                clearSsoFlow(stateRef.current);
                navigate(response.return_path ?? SECURITY_SETTINGS_PATH, {replace: true, state: {ssoConnected: response.provider_name}});
                return;
            case 'REAUTHENTICATED':
                clearSsoFlow(stateRef.current);
                storeReauthProof(response.reauth_proof as string);
                navigate(response.return_path ?? SECURITY_SETTINGS_PATH, {replace: true});
                return;
        }
    };

    const handleProviderError = (providerError: string, currentFlow: SsoFlow | null) => {
        if (currentFlow?.intent === 'TEST') {
            finishTest({error: providerError === 'access_denied'
                    ? t`The test sign-in was cancelled.`
                    : t`Your identity provider returned an error: ${providerError}`}, currentFlow);
            return;
        }

        if (isAccountConnection(currentFlow?.provider) && SILENT_SIGN_IN_ERRORS.includes(providerError)) {
            clearSsoFlow(stateRef.current);
            navigate('/auth/login', {replace: true, state: {ssoStep: true}});
            return;
        }

        if (providerError !== 'access_denied') {
            fail({error_code: 'SSO_PROVIDER_ERROR', message: t`The sign-in couldn't be completed. Please try again.`});
            return;
        }

        clearSsoFlow(stateRef.current);

        if (isSelfService(currentFlow)) {
            navigate(currentFlow?.returnPath ?? SECURITY_SETTINGS_PATH, {replace: true});
        } else if (currentFlow?.intent === 'INVITE' && currentFlow.invitationToken) {
            navigate(`/auth/accept-invitation/${currentFlow.invitationToken}`, {replace: true});
        } else {
            navigate('/auth/login?sso_cancelled=1', {replace: true});
        }
    };

    useEffect(() => {
        if (startedRef.current) {
            return;
        }

        startedRef.current = true;

        const params = new URLSearchParams(window.location.search);
        const state = params.get('state') ?? window.history.state?.ssoState ?? null;
        const code = params.get('code');
        const providerError = params.get('error');
        const currentFlow = getSsoFlow(state);

        window.history.replaceState({ssoState: state}, '', window.location.pathname);
        stateRef.current = state;
        setFlow(currentFlow);

        if (providerError) {
            handleProviderError(providerError, currentFlow);
            return;
        }

        if (!currentFlow || !code || !state) {
            userClient.me()
                .then(() => window.location.replace('/manage/events'))
                .catch(() => fail({error_code: 'SSO_FLOW_MISSING', message: ''}));
            return;
        }

        const slowTimer = setTimeout(() => setIsSlow(true), SLOW_EXCHANGE_MS);

        exchangeMutation.mutate({
            request: {code, state, binding: currentFlow.binding},
            authenticated: isSelfService(currentFlow),
        }, {
            onSuccess: response => handleExchange(response, currentFlow),
            onError: error => currentFlow.intent === 'TEST'
                ? finishTest({error: ssoError(error).message}, currentFlow)
                : fail(ssoError(error)),
            onSettled: () => clearTimeout(slowTimer),
        });
    }, []);

    const exitPath = isSelfService(flow) ? (flow?.returnPath ?? SECURITY_SETTINGS_PATH) : '/auth/login';

    const backToSignIn: SsoErrorAction = {
        label: isSelfService(flow) ? t`Back to security settings` : t`Back to sign in`,
        onClick: () => navigate(exitPath),
    };

    const restart = (switchAccount: boolean) => {
        if (!flow || isSelfService(flow)) {
            backToSignIn.onClick();
            return;
        }

        const intent = flow.intent as 'LOGIN' | 'SIGNUP' | 'INVITE';
        const provider = sso?.providers.find(item => item.key === flow.provider);

        if (!provider) {
            backToSignIn.onClick();
            return;
        }

        startSso.mutate({
            provider,
            request: {
                intent,
                return_path: flow.returnPath,
                invitation_token: flow.invitationToken,
                timezone: flow.timezone,
                switch_account: switchAccount,
            },
            partnerInviteToken: flow.partnerInviteToken,
        }, {
            onError: error => fail(ssoError(error)),
        });
    };

    const tryAgain: SsoErrorAction = {
        label: t`Try again`,
        testId: 'sso-try-again',
        onClick: () => restart(false),
    };

    const useAnotherAccount: SsoErrorAction = {
        label: t`Use a different account`,
        onClick: () => restart(true),
    };

    const errorActions = (error: SsoErrorResponse): { primary: SsoErrorAction, secondary?: SsoErrorAction } => {
        const signInWithPassword: SsoErrorAction = {
            label: t`Sign in with password`,
            onClick: () => navigate('/auth/login', {state: {email: error.context?.email}}),
        };

        switch (error.error_code) {
            case 'SSO_REQUIRED':
                return {
                    primary: {
                        label: t`Sign in with SSO`,
                        testId: 'sso-required-continue',
                        onClick: () => {
                            clearSsoFlow(stateRef.current);
                            navigate('/auth/login', {state: {ssoStep: true, email: error.context?.email}});
                        },
                    },
                    secondary: backToSignIn,
                };
            case 'SSO_LINK_REQUIRES_LOGIN':
            case 'SSO_EMAIL_TAKEN':
                return passwordLoginEnabled ? {primary: signInWithPassword} : {primary: useAnotherAccount, secondary: backToSignIn};
            case 'SSO_INVITATION_PENDING':
            case 'SSO_NO_ACTIVE_ACCOUNT':
            case 'SSO_REGISTRATION_DISABLED':
            case 'SSO_PROVIDER_NOT_CONFIGURED':
            case 'SSO_INVITE_INVALID':
            case 'SSO_REAUTH_FAILED':
            case 'SSO_FLOW_MISSING':
                return {primary: backToSignIn};
            case 'SSO_IDENTITY_IN_USE':
                return isSelfService(flow) ? {primary: backToSignIn} : {primary: useAnotherAccount, secondary: backToSignIn};
            case 'SSO_INVITE_EMAIL_MISMATCH':
                return {
                    primary: useAnotherAccount,
                    secondary: passwordLoginEnabled ? {
                        label: t`Use a password instead`,
                        onClick: () => navigate(`/auth/accept-invitation/${flow?.invitationToken}`),
                    } : undefined,
                };
            case 'SSO_INVITE_SIGN_IN_REQUIRED':
                return {
                    primary: {
                        label: t`Sign in`,
                        onClick: () => {
                            setPreviousUrl(`/auth/accept-invitation/${flow?.invitationToken}`);
                            navigate('/auth/login');
                        },
                    },
                };
            default:
                return {primary: tryAgain, secondary: backToSignIn};
        }
    };

    const renderView = () => {
        if (view.kind === 'error') {
            return (
                <SsoErrorState
                    error={view.error}
                    providerName={flow?.providerName ?? ''}
                    passwordLoginEnabled={passwordLoginEnabled}
                    {...errorActions(view.error)}
                />
            );
        }

        if (view.kind === 'signup' && flow) {
            return (
                <CompleteSignup
                    signup={view.signup}
                    flow={flow}
                    providerName={view.providerName}
                    onFailed={fail}
                    onUseAnotherAccount={useAnotherAccount.onClick}
                    onSignedUp={() => {
                        markSsoSignupConversion();
                        clearStoredUtmData();
                        clearSsoFlow(stateRef.current);
                        window.location.replace('/welcome');
                    }}
                />
            );
        }

        if (view.kind === 'steps') {
            if (steps.step === 'start') {
                return (
                    <SsoErrorState
                        error={steps.error
                            ? {error_code: 'SSO_SIGN_IN_FAILED', message: steps.error}
                            : {error_code: 'SSO_TICKET_INVALID', message: ''}}
                        providerName={flow?.providerName ?? ''}
                        passwordLoginEnabled={passwordLoginEnabled}
                        primary={tryAgain}
                        secondary={backToSignIn}
                    />
                );
            }

            return <LoginStepView steps={steps} onBack={() => navigate('/auth/login')}/>;
        }

        return (
            <div className={classes.status}>
                <div className={classes.statusIcon}><Loader size={22} color="gray"/></div>
                <h2 className={classes.statusTitle}>
                    {flow ? t`Signing you in with ${flow.providerName}…` : t`Signing you in…`}
                </h2>
                <p className={classes.statusText} aria-live="polite">
                    {isSlow ? t`Still working. This can take a few seconds.` : ''}
                </p>
                {isSlow && <a className={classes.startOver} href={exitPath}>{t`Start over`}</a>}
            </div>
        );
    };

    return (
        <AuthShell isStatic>
            {renderView()}
        </AuthShell>
    );
};

export default SsoCallback;
