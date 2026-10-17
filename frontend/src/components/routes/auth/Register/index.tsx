import {Button, Checkbox, PasswordInput, SimpleGrid, TextInput} from "@mantine/core";
import {hasLength, isEmail, matchesField, useForm} from "@mantine/form";
import {RegisterAccountRequest} from "../../../../types.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {useRegisterAccount} from "../../../../mutations/useRegisterAccount.ts";
import {NavLink, useLocation, useNavigate} from "react-router";
import {t, Trans} from "@lingui/macro";
import classes from "./Register.module.scss";
import {getClientLocale} from "../../../../locales.ts";
import {useEffect, useState} from "react";
import {getUserCurrency} from "../../../../utilites/currency.ts";
import {getConfig} from "../../../../utilites/config.ts";
import {getStoredUtmData, clearStoredUtmData} from "../../../../utilites/utm.ts";
import {Turnstile} from "../../../common/Turnstile";
import {Callout} from "../../../common/Callout";
import {SsoSection, useSsoOptions} from "../../../common/SsoButtons/SsoSection.tsx";


export const Register = () => {
    const navigate = useNavigate();
    const location = useLocation();

    const form = useForm({
        validateInputOnBlur: true,
        initialValues: {
            first_name: '',
            last_name: '',
            email: '',
            password: '',
            password_confirmation: '',
            timezone: typeof window !== 'undefined'
                ? Intl.DateTimeFormat().resolvedOptions().timeZone
                : 'UTC',
            locale: getClientLocale(),
            invite_token: '',
            currency_code: getUserCurrency(),
            marketing_opt_in: false,
        },
        validate: {
            password: hasLength({min: 8}, t`Password must be at least 8 characters`),
            password_confirmation: matchesField('password', t`Passwords are not the same`),
            email: isEmail(t`Please check your email is valid`),
        },
    });
    const errorHandler = useFormErrorResponseHandler();
    const mutate = useRegisterAccount();
    const [turnstileToken, setTurnstileToken] = useState('');
    const [formError, setFormError] = useState<string | null>(null);
    const [ssoRequiredEmail, setSsoRequiredEmail] = useState<string | null>(null);
    const {passwordLoginEnabled, registrationEnabled} = useSsoOptions();
    const [turnstileResetKey, setTurnstileResetKey] = useState(0);

    const registerUser = (data: RegisterAccountRequest) => {
        const utmData = getStoredUtmData();
        const registrationData = {
            ...data,
            ...(utmData ?? {}),
            ...(turnstileToken ? {turnstile_token: turnstileToken} : {}),
        };

        mutate.mutate({registerData: registrationData}, {
            onSuccess: () => {
                clearStoredUtmData();
                navigate(`/welcome${location.search}`);
            },
            onError: (error: any) => {
                setTurnstileResetKey((key) => key + 1);

                if (error?.response?.data?.error_code === 'SSO_REQUIRED') {
                    setSsoRequiredEmail(data.email);
                    return;
                }

                if (error?.response?.data?.errors) {
                    errorHandler(form, error);
                    return;
                }

                setFormError(error?.response?.status === 429
                    ? t`Too many attempts. Please wait a minute and try again.`
                    : error?.response?.data?.message ?? t`Something went wrong. Please try again.`);
            },
        });
    }

    useEffect(() => {
        const searchParams = new URLSearchParams(location.search);
        const token = searchParams.get('invite_token');

        if (token) {
            form.setFieldValue('invite_token', token);
        }
    }, [location.search]);

    return (
        <>
            <header className={classes.header}>
                <h2>{t`Get started`}</h2>
                <p>
                    <Trans>
                        Already have an account?{' '}
                        <NavLink to={`/auth/login${location.search}`}>
                            {t`Sign in`}
                        </NavLink>
                    </Trans>
                </p>
            </header>

            {ssoRequiredEmail !== null && (
                <Callout variant="info" title={t`Your organization uses single sign-on`}>
                    {t`Accounts for this email address are managed by your organization. Sign in through its identity provider instead of creating a new account.`}
                    <div>
                        <Button size="compact-sm" variant="light" mt="xs" data-testid="register-sso-required-button"
                                onClick={() => navigate('/auth/login', {state: {ssoStep: true, email: ssoRequiredEmail}})}>
                            {t`Sign in with SSO`}
                        </Button>
                    </div>
                </Callout>
            )}
            {formError && (
                <Callout variant="danger" onDismiss={() => setFormError(null)}>{formError}</Callout>
            )}
            <SsoSection
                request={{intent: 'SIGNUP'}}
                variant="signUp"
                onError={setFormError}
                partnerInviteToken={new URLSearchParams(location.search).get('invite_token') ?? undefined}
                disabled={mutate.isPending}
                hidden={!registrationEnabled}
            />

            <div className={classes.registerCard}>
                {passwordLoginEnabled && (
                <form onSubmit={form.onSubmit((values) => registerUser(values as RegisterAccountRequest))}>

                    <SimpleGrid verticalSpacing={{base: "md", lg: 0}} cols={{base: 1, lg: 2}} mb="md">
                        <TextInput
                            {...form.getInputProps('first_name')}
                            label={t`First Name`}
                            placeholder={t`John`}
                            required
                        />
                        <TextInput
                            {...form.getInputProps('last_name')}
                            label={t`Last Name`}
                            placeholder={t`Smith`}
                        />
                    </SimpleGrid>

                    <TextInput
                        mb={0}
                        {...form.getInputProps('email')}
                        label={t`Email`}
                        placeholder={'your@email.com'}
                        required
                    />

                    <SimpleGrid verticalSpacing={{base: "md", lg: 0}} cols={{base: 1, lg: 2}} mt="md" mb="md">
                        <PasswordInput
                            {...form.getInputProps('password')}
                            label={t`Password`}
                            placeholder={t`Your password`}
                            required
                        />
                        <PasswordInput
                            {...form.getInputProps('password_confirmation')}
                            label={t`Confirm Password`}
                            placeholder={t`Confirm password`}
                            required
                        />
                    </SimpleGrid>

                    <TextInput
                        style={{display: 'none'}}
                        {...form.getInputProps('timezone')}
                        type="hidden"
                    />

                    <Checkbox
                        mb="md"
                        {...form.getInputProps('marketing_opt_in', {type: 'checkbox'})}
                        label={<Trans>Receive product updates from {getConfig("VITE_APP_NAME", "Hi.Events")}.</Trans>}
                    />

                    <Turnstile onToken={setTurnstileToken} resetKey={turnstileResetKey}/>

                    <Button color="secondary.5" type="submit" fullWidth disabled={mutate.isPending}>
                        {mutate.isPending ? t`Working...` : t`Register`}
                    </Button>
                </form>
                )}
                <footer>
                    <Trans>
                        By continuing you agree to our <NavLink target={'_blank'}
                                                                 to={getConfig("VITE_TOS_URL", "https://hi.events/terms-of-service?utm_source=app-register-footer") as string}>Terms
                        of Service</NavLink> and <NavLink
                        target={'_blank'}
                        to={getConfig("VITE_PRIVACY_URL", 'https://hi.events/privacy-policy?utm_source=app-register-footer') as string}>Privacy Policy</NavLink>.
                    </Trans>
                </footer>
            </div>
        </>
    )
}

export default Register;
