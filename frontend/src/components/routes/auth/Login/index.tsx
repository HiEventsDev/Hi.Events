import {Button, Collapse, PasswordInput, TextInput, UnstyledButton} from "@mantine/core";
import {NavLink, useLocation} from "react-router";
import {useMutation} from "@tanstack/react-query";
import {authClient} from "../../../../api/auth.client.ts";
import {LoginData, LoginResponse} from "../../../../types.ts";
import {useForm} from "@mantine/form";
import classes from "./Login.module.scss";
import {t, Trans} from "@lingui/macro";
import {useEffect, useState} from "react";
import {useSendTicketLookupEmail} from "../../../../mutations/useSendTicketLookupEmail.ts";
import {IconTicket, IconChevronDown} from "@tabler/icons-react";
import {LoginStepView} from "./LoginStepView.tsx";
import {useLoginSteps} from "../../../../hooks/useLoginSteps.ts";
import {SsoSection, useSsoOptions} from "../../../common/SsoButtons/SsoSection.tsx";
import {EnterpriseSsoButton, SsoDiscovery} from "../../../../ee/sso/components/SsoDiscovery/index.tsx";
import {useStartSso} from "../../../../mutations/useSsoMutations.ts";
import {takeAuthNotice, takeSilentReauthProvider} from "../../../../utilites/ssoFlow.ts";
import {Callout} from "../../../common/Callout";
import {getConfig} from "../../../../utilites/config.ts";

const Login = () => {
    const location = useLocation();
    const form = useForm({
        initialValues: {
            email: (location.state as { email?: string } | null)?.email ?? '',
            password: '',
        }
    });
    const [ticketLookupOpen, setTicketLookupOpen] = useState(false);
    const [ssoCancelled, setSsoCancelled] = useState(false);
    const [notice, setNotice] = useState<string | null>((location.state as { notice?: string } | null)?.notice ?? null);
    const steps = useLoginSteps();
    const {passwordLoginEnabled, registrationEnabled, enterpriseSsoEnabled} = useSsoOptions();
    const [ssoDiscoveryEmail, setSsoDiscoveryEmail] = useState<string | null>((location.state as { ssoStep?: boolean } | null)?.ssoStep
        ? (location.state as { email?: string }).email ?? ''
        : null);
    const [ssoRequiredEmail, setSsoRequiredEmail] = useState<string | null>(null);
    const startSso = useStartSso();

    const ticketLookupForm = useForm({
        initialValues: {
            email: '',
        }
    });
    const [ticketLookupSuccess, setTicketLookupSuccess] = useState(false);

    useEffect(() => {
        const silentProvider = takeSilentReauthProvider();

        if (silentProvider) {
            startSso.mutate({
                provider: {key: silentProvider, name: t`SSO`, type: 'OIDC', logo_url: null},
                request: {intent: 'LOGIN', silent: true},
            }, {onError: () => setSsoDiscoveryEmail('')});
        }

        if (takeAuthNotice() === 'SSO_REQUIRED') {
            setSsoRequiredEmail('');
        }

        const params = new URLSearchParams(location.search);

        if (params.has('sso_cancelled')) {
            setSsoCancelled(true);
            params.delete('sso_cancelled');
            const query = params.toString();
            window.history.replaceState(window.history.state, '', location.pathname + (query ? `?${query}` : ''));
        }
    }, []);

    const {mutate: loginUser, isPending} = useMutation({
        mutationFn: (userData: LoginData) => authClient.login(userData),

        onSuccess: (response: LoginResponse, variables: LoginData) => {
            steps.handleResponse(response, (accountId) => loginUser({...variables, account_id: accountId as string}));
        },

        onError: (error: any, variables: LoginData) => {
            steps.setPendingAccountId(null);

            if (error?.response?.data?.error_code === 'SSO_REQUIRED') {
                setSsoRequiredEmail(variables.email);
                return;
            }

            steps.setError(error?.response?.status === 429
                ? t`Too many attempts. Please wait a minute and try again.`
                : t`Please check your email and password and try again`);
        }
    });

    const ticketLookupMutation = useSendTicketLookupEmail();

    if (steps.step !== 'start') {
        return <LoginStepView steps={steps} onBack={() => steps.reset()}/>;
    }

    if (ssoDiscoveryEmail !== null) {
        return <SsoDiscovery initialEmail={ssoDiscoveryEmail} onBack={() => setSsoDiscoveryEmail(null)}/>;
    }

    const handleTicketLookup = (values: { email: string }) => {
        ticketLookupMutation.mutate(values.email, {
            onSuccess: () => {
                setTicketLookupSuccess(true);
            },
            onError: () => {
                ticketLookupForm.setFieldError('email', t`Something went wrong. Please try again.`);
            }
        });
    };

    return (
        <>
            <header className={classes.header}>
                <h2>{passwordLoginEnabled ? t`Welcome back` : t`Sign in to ${getConfig("VITE_APP_NAME", "Hi.Events")}`}</h2>
                {registrationEnabled && (
                    <p>
                        <Trans>
                            Don't have an account?{' '}
                            <NavLink to={`/auth/register${location.search}`}>
                                Sign up
                            </NavLink>
                        </Trans>
                    </p>
                )}
            </header>
            {notice && (
                <Callout variant="info" onDismiss={() => setNotice(null)}>{notice}</Callout>
            )}
            {ssoCancelled && (
                <Callout variant="info" onDismiss={() => setSsoCancelled(false)}>
                    {t`Sign-in was cancelled. Choose another way to sign in, or try again.`}
                </Callout>
            )}
            {ssoRequiredEmail !== null && (
                <Callout variant="danger" title={t`Your organization requires single sign-on`}>
                    {t`Sign in through your organization's identity provider instead of using a password.`}
                    <div>
                        <Button size="compact-sm" variant="light" mt="xs" onClick={() => setSsoDiscoveryEmail(ssoRequiredEmail)}>
                            {t`Sign in with SSO`}
                        </Button>
                    </div>
                </Callout>
            )}
            {steps.error && (
                <Callout variant="danger" className={classes.formError} onDismiss={() => steps.setError(null)}>
                    {steps.error}
                </Callout>
            )}
            <SsoSection
                request={{intent: 'LOGIN'}}
                onError={steps.setError}
                disabled={isPending}
                trailing={enterpriseSsoEnabled && (
                    <EnterpriseSsoButton onClick={() => setSsoDiscoveryEmail(form.values.email)} disabled={isPending}/>
                )}
            />
            {passwordLoginEnabled && (
                <div className={classes.loginCard}>
                    <form onSubmit={form.onSubmit((values) => loginUser(values))}>
                        <TextInput {...form.getInputProps('email')}
                                   label={t`Email`}
                                   placeholder="you@example.com"
                                   type="email"
                                   autoComplete="email"
                                   required
                        />
                        <div className={classes.passwordLabelRow}>
                            <label htmlFor="login-password">{t`Password`}</label>
                            <NavLink to={`/auth/forgot-password`}>
                                {t`Forgot password?`}
                            </NavLink>
                        </div>
                        <PasswordInput {...form.getInputProps('password')}
                                       id="login-password"
                                       placeholder={t`Your password`}
                                       autoComplete="current-password"
                                       required
                        />
                        <Button color="secondary.5" type="submit" fullWidth loading={isPending} disabled={isPending} mt="lg">
                            {isPending ? t`Signing in` : t`Sign in`}
                        </Button>
                    </form>
                </div>
            )}

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

        </>
    )
}

export default Login;
