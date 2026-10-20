import {Button, Checkbox, TextInput, UnstyledButton} from "@mantine/core";
import {useForm} from "@mantine/form";
import {t, Trans} from "@lingui/macro";
import {IconArrowLeft, IconMailCheck, IconUserPlus} from "@tabler/icons-react";
import {NavLink} from "react-router";
import {useEffect, useState} from "react";
import {useCompleteSsoSignup, useSendSsoSignupCode} from "../../../../mutations/useSsoMutations.ts";
import {getClientLocale, SupportedLocales} from "../../../../locales.ts";
import {getUserCurrency} from "../../../../utilites/currency.ts";
import {getConfig} from "../../../../utilites/config.ts";
import {getStoredUtmData} from "../../../../utilites/utm.ts";
import {SsoFlow} from "../../../../utilites/ssoFlow.ts";
import {ssoError} from "../../../../utilites/ssoErrors.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {SsoErrorResponse, SsoSignupDetails} from "../../../../types.ts";
import {OneTimeCodeInput} from "../../../common/OneTimeCodeInput";
import {Callout} from "../../../common/Callout";
import loginClasses from "../../auth/Login/Login.module.scss";
import classes from "./SsoCallback.module.scss";

const RESEND_COOLDOWN_SECONDS = 30;

interface CompleteSignupProps {
    signup: SsoSignupDetails;
    flow: SsoFlow;
    providerName: string;
    onSignedUp: () => void;
    onFailed: (error: SsoErrorResponse) => void;
    onUseAnotherAccount: () => void;
}

export const CompleteSignup = ({signup, flow, providerName, onSignedUp, onFailed, onUseAnotherAccount}: CompleteSignupProps) => {
    const [needsCode, setNeedsCode] = useState(false);
    const [code, setCode] = useState('');
    const [codeError, setCodeError] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [resendIn, setResendIn] = useState(0);
    const sendCodeMutation = useSendSsoSignupCode();
    const signupMutation = useCompleteSsoSignup();
    const errorHandler = useFormErrorResponseHandler();
    const form = useForm({
        initialValues: {
            first_name: signup.first_name ?? '',
            last_name: signup.last_name ?? '',
            marketing_opt_in: false,
        },
        validate: {
            first_name: (value) => value.trim().length === 0 ? t`Enter your first name` : null,
        },
    });

    useEffect(() => {
        if (resendIn <= 0) {
            return;
        }

        const timer = setTimeout(() => setResendIn(resendIn - 1), 1000);

        return () => clearTimeout(timer);
    }, [resendIn]);

    const sendCode = () => {
        setCodeError(null);
        sendCodeMutation.mutate({signup_ticket: signup.ticket, binding: flow.binding, locale: getClientLocale()}, {
            onSuccess: () => {
                setResendIn(RESEND_COOLDOWN_SECONDS);
                setNeedsCode(true);
            },
            onError: (requestError) => {
                const failure = ssoError(requestError);
                failure.error_code === 'RATE_LIMITED' ? setCodeError(failure.message) : onFailed(failure);
            },
        });
    };

    const submit = (emailCode?: string) => {
        if (!signup.email_verified && !emailCode) {
            sendCode();
            return;
        }

        setError(null);
        signupMutation.mutate({
            ...(getStoredUtmData() ?? {}),
            signup_ticket: signup.ticket,
            binding: flow.binding,
            first_name: form.values.first_name.trim(),
            last_name: form.values.last_name.trim() || undefined,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            currency_code: getUserCurrency(),
            locale: getClientLocale() as SupportedLocales,
            marketing_opt_in: form.values.marketing_opt_in,
            email_code: emailCode,
            invite_token: flow.partnerInviteToken,
        }, {
            onSuccess: onSignedUp,
            onError: (requestError: any) => {
                const failure = ssoError(requestError);

                if (failure.error_code === 'SSO_EMAIL_CODE_INVALID') {
                    setCode('');
                    setCodeError(failure.message);
                    return;
                }

                if (requestError?.response?.status === 422) {
                    setNeedsCode(false);
                    errorHandler(form, requestError);
                    return;
                }

                if (failure.error_code === 'RATE_LIMITED' || failure.error_code === 'UNKNOWN') {
                    setError(failure.message);
                    return;
                }

                onFailed(failure);
            },
        });
    };

    if (needsCode) {
        return (
            <div className={loginClasses.challenge}>
                <header className={loginClasses.challengeHeader}>
                    <div className={loginClasses.challengeIcon}><IconMailCheck size={26} stroke={1.75}/></div>
                    <h2>{t`Confirm your email`}</h2>
                    <p><Trans>Enter the 6-digit code we sent to <strong>{signup.email}</strong></Trans></p>
                </header>
                <OneTimeCodeInput
                    label={t`Confirmation code`}
                    value={code}
                    onChange={(value) => {
                        setCode(value);
                        setCodeError(null);
                    }}
                    onComplete={(value) => submit(value)}
                    error={codeError}
                    disabled={signupMutation.isPending}
                    autoFocus
                    dataTestId="sso-signup-code"
                />
                <p className={classes.resend}>
                    {t`Didn't get it?`}{' '}
                    <UnstyledButton onClick={sendCode} disabled={resendIn > 0}>
                        {resendIn > 0 ? t`Resend in ${resendIn}s` : t`Resend code`}
                    </UnstyledButton>
                </p>
                <UnstyledButton className={loginClasses.backToLogin} onClick={() => setNeedsCode(false)}>
                    <IconArrowLeft size={16}/>
                    {t`Back`}
                </UnstyledButton>
                <UnstyledButton className={classes.notYou} onClick={onUseAnotherAccount}>{t`Use a different account`}</UnstyledButton>
            </div>
        );
    }

    return (
        <div className={loginClasses.challenge}>
            <header className={loginClasses.challengeHeader}>
                <div className={loginClasses.challengeIcon}><IconUserPlus size={26} stroke={1.75}/></div>
                <h2>{t`One last step`}</h2>
                <p>{t`Check your name and we'll create your account.`}</p>
            </header>

            <div className={classes.identity}>
                <span className={classes.avatar} aria-hidden="true">{signup.email.charAt(0)}</span>
                <span className={classes.identityText}>
                    <span className={classes.identityEmail} title={signup.email}>{signup.email}</span>
                    <span className={classes.identityVia}>{t`via ${providerName}`}</span>
                </span>
                <UnstyledButton className={classes.notYou} onClick={onUseAnotherAccount}>{t`Not you?`}</UnstyledButton>
            </div>

            {error && <Callout variant="danger" onDismiss={() => setError(null)}>{error}</Callout>}

            <div className={classes.signupCard}>
                <form onSubmit={form.onSubmit(() => submit())}>
                    <div className={classes.nameFields}>
                        <TextInput
                            {...form.getInputProps('first_name')}
                            label={t`First name`}
                            autoComplete="given-name"
                            autoFocus={!form.values.first_name}
                            required
                        />
                        <TextInput
                            {...form.getInputProps('last_name')}
                            label={t`Last name`}
                            autoComplete="family-name"
                            autoFocus={!!form.values.first_name && !form.values.last_name}
                        />
                    </div>
                    <Checkbox
                        mb="md"
                        {...form.getInputProps('marketing_opt_in', {type: 'checkbox'})}
                        label={<Trans>Receive product updates from {getConfig("VITE_APP_NAME", "Hi.Events")}.</Trans>}
                    />
                    <Button color="secondary.5" type="submit" fullWidth loading={signupMutation.isPending || sendCodeMutation.isPending} data-testid="sso-complete-signup-submit">
                        {t`Create account`}
                    </Button>
                </form>
                <footer className={classes.terms}>
                    <Trans>
                        By continuing you agree to the <NavLink target="_blank"
                                                                to={getConfig("VITE_TOS_URL", "https://hi.events/terms-of-service?utm_source=app-register-footer") as string}>Terms
                        of Service</NavLink> and <NavLink target="_blank"
                                                          to={getConfig("VITE_PRIVACY_URL", "https://hi.events/privacy-policy?utm_source=app-register-footer") as string}>Privacy Policy</NavLink>.
                    </Trans>
                </footer>
            </div>
        </div>
    );
};
