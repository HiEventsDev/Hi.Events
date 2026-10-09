import {Button, PasswordInput, TextInput, Collapse, UnstyledButton} from "@mantine/core";
import {NavLink, useLocation} from "react-router";
import {useMutation} from "@tanstack/react-query";
import {authClient} from "../../../../api/auth.client.ts";
import {IdParam, LoginData, LoginResponse, TwoFactorLoginRequest} from "../../../../types.ts";
import {useForm} from "@mantine/form";
import {redirectToPreviousUrl, setPreviousUrl} from "../../../../api/client.ts";
import classes from "./Login.module.scss";
import {t, Trans} from "@lingui/macro";
import {useEffect, useState} from "react";
import {ChooseAccountModal} from "../../../modals/ChooseAccountModal";
import {useSendTicketLookupEmail} from "../../../../mutations/useSendTicketLookupEmail.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import {IconTicket, IconChevronDown} from "@tabler/icons-react";
import {TwoFactorChallenge} from "./TwoFactorChallenge.tsx";
import {RecoveryCodeNotice} from "./RecoveryCodeNotice.tsx";

type LoginStep = 'credentials' | 'two-factor' | 'recovery-notice';

const Login = () => {
    const location = useLocation();
    const form = useForm({
        initialValues: {
            email: '',
            password: '',
            account_id: '',
        }
    });
    const [showChooseAccount, setShowChooseAccount] = useState(false);
    const [step, setStep] = useState<LoginStep>('credentials');
    const [challengeToken, setChallengeToken] = useState<string | null>(null);
    const [twoFactorError, setTwoFactorError] = useState<string | null>(null);
    const [accounts, setAccounts] = useState<LoginResponse['accounts']>([]);
    const [recoveryCodesRemaining, setRecoveryCodesRemaining] = useState(0);
    const [ticketLookupOpen, setTicketLookupOpen] = useState(false);

    const ticketLookupForm = useForm({
        initialValues: {
            email: '',
        }
    });
    const [ticketLookupSuccess, setTicketLookupSuccess] = useState(false);

    const handleAuthenticated = (response: LoginResponse) => {
        if (response.token) {
            if (response.two_factor_recovery_codes_remaining !== undefined) {
                setRecoveryCodesRemaining(response.two_factor_recovery_codes_remaining);
                setStep('recovery-notice');
                return;
            }
            redirectToPreviousUrl();
            return;
        }

        if (response.accounts.length > 1) {
            setAccounts(response.accounts);
            setShowChooseAccount(true);
        }
    };

    const resetToCredentials = (message?: string) => {
        setStep('credentials');
        setChallengeToken(null);
        setTwoFactorError(null);
        setShowChooseAccount(false);
        form.setFieldValue('account_id', '');
        if (message) {
            showError(message);
        }
    };

    const {mutate: loginUser, isPending} = useMutation({
        mutationFn: (userData: LoginData) => authClient.login(userData),

        onSuccess: (response: LoginResponse) => {
            if (response.two_factor_required && response.two_factor_challenge_token) {
                setChallengeToken(response.two_factor_challenge_token);
                setTwoFactorError(null);
                setStep('two-factor');
                return;
            }

            handleAuthenticated(response);
        },

        onError: (error: any) => {
            showError(error?.response?.status === 429
                ? t`Too many attempts. Please wait a minute and try again.`
                : t`Please check your email and password and try again`);
        }
    });

    const {mutate: verifyTwoFactor, isPending: isVerifying} = useMutation({
        mutationFn: (request: TwoFactorLoginRequest) => authClient.loginWithTwoFactor(request),

        onSuccess: handleAuthenticated,

        onError: (error: any) => {
            const status = error?.response?.status;
            const data = error?.response?.data;

            if (status === 401) {
                resetToCredentials(data?.message ?? t`Your sign-in session has expired. Please sign in again.`);
                return;
            }

            if (status === 429) {
                setTwoFactorError(t`Too many attempts. Please wait a minute and try again.`);
                return;
            }

            setTwoFactorError(data?.errors?.code?.[0]
                ?? data?.errors?.recovery_code?.[0]
                ?? t`Something went wrong. Please try again.`);
        }
    });

    const chooseAccount = (accountId: IdParam) => {
        if (challengeToken) {
            verifyTwoFactor({challenge_token: challengeToken, account_id: accountId});
            return;
        }

        form.setFieldValue('account_id', accountId as string);
    };

    const ticketLookupMutation = useSendTicketLookupEmail();

    useEffect(() => {
        form.values.account_id && loginUser(form.values);
    }, [form.values.account_id]);

    if (step === 'recovery-notice') {
        return (
            <RecoveryCodeNotice
                remaining={recoveryCodesRemaining}
                onManageCodes={() => {
                    setPreviousUrl('/manage/profile/security');
                    redirectToPreviousUrl();
                }}
                onContinue={redirectToPreviousUrl}
            />
        );
    }

    if (step === 'two-factor' && challengeToken) {
        return (
            <>
                <TwoFactorChallenge
                    isSubmitting={isVerifying}
                    error={twoFactorError}
                    onClearError={() => setTwoFactorError(null)}
                    onSubmit={(payload) => verifyTwoFactor({challenge_token: challengeToken, ...payload})}
                    onBack={() => resetToCredentials()}
                />
                {showChooseAccount && <ChooseAccountModal onAccountChosen={chooseAccount} accounts={accounts}/>}
            </>
        );
    }

    const handleTicketLookup = (values: { email: string }) => {
        ticketLookupMutation.mutate(values.email, {
            onSuccess: () => {
                setTicketLookupSuccess(true);
            },
            onError: () => {
                showError(t`Something went wrong. Please try again.`);
            }
        });
    };

    return (
        <>
            <header className={classes.header}>
                <h2>{t`Welcome back`}</h2>
                <p>
                    <Trans>
                        Don't have an account?{' '}
                        <NavLink to={`/auth/register${location.search}`}>
                            Sign up
                        </NavLink>
                    </Trans>
                </p>
            </header>
            <div className={classes.loginCard}>
                <form onSubmit={form.onSubmit((values) => loginUser(values))}>
                    <TextInput {...form.getInputProps('email')}
                               label={t`Email`}
                               placeholder="you@example.com"
                               required
                    />
                    <div className={classes.passwordLabelRow}>
                        <label htmlFor="login-password">{t`Password`}</label>
                        <NavLink to={`/auth/forgot-password`} tabIndex={-1}>
                            {t`Forgot password?`}
                        </NavLink>
                    </div>
                    <PasswordInput {...form.getInputProps('password')}
                                   id="login-password"
                                   placeholder={t`Your password`}
                                   required
                    />
                    <Button color="secondary.5" type="submit" fullWidth loading={isPending} disabled={isPending} mt="lg">
                        {isPending ? t`Logging in` : t`Log in`}
                    </Button>
                </form>
            </div>

            <div className={classes.ticketLookup}>
                <UnstyledButton
                    className={classes.ticketLookupTrigger}
                    onClick={() => setTicketLookupOpen(!ticketLookupOpen)}
                    data-expanded={ticketLookupOpen}
                >
                    <IconTicket size={18} />
                    <span>{t`Just looking for your tickets?`}</span>
                    <IconChevronDown
                        size={16}
                        className={classes.chevron}
                        data-expanded={ticketLookupOpen}
                    />
                </UnstyledButton>

                <Collapse expanded={ticketLookupOpen}>
                    <div className={classes.ticketLookupContent}>
                        {ticketLookupSuccess ? (
                            <div className={classes.successMessage}>
                                <p>{t`Check your inbox! If tickets are associated with this email, you'll receive a link to view them.`}</p>
                                <UnstyledButton
                                    className={classes.resetLink}
                                    onClick={() => {
                                        setTicketLookupSuccess(false);
                                        ticketLookupForm.reset();
                                    }}
                                >
                                    {t`Try another email`}
                                </UnstyledButton>
                            </div>
                        ) : (
                            <form onSubmit={ticketLookupForm.onSubmit(handleTicketLookup)}>
                                <div className={classes.ticketLookupForm}>
                                    <TextInput
                                        {...ticketLookupForm.getInputProps('email')}
                                        type="email"
                                        placeholder={t`Enter your email`}
                                        required
                                        className={classes.ticketEmailInput}
                                    />
                                    <Button
                                        type="submit"
                                        color="secondary.5"
                                        loading={ticketLookupMutation.isPending}
                                        disabled={ticketLookupMutation.isPending}
                                    >
                                        {t`Send`}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </div>
                </Collapse>
            </div>

            {showChooseAccount && <ChooseAccountModal onAccountChosen={chooseAccount} accounts={accounts}/>}
        </>
    )
}

export default Login;
