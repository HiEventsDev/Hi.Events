import {Button, PasswordInput, SimpleGrid, TextInput} from "@mantine/core";
import {hasLength, isEmail, matchesField, useForm} from "@mantine/form";
import {RegisterAccountRequest} from "../../../../types.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {useRegisterAccount} from "../../../../mutations/useRegisterAccount.ts";
import {NavLink, useLocation, useNavigate} from "react-router";
import {t, Trans} from "@lingui/macro";
import {InputGroup} from "../../../common/InputGroup";
import {Card} from "../../../common/Card";
import classes from "./Register.module.scss";
import {getClientLocale} from "../../../../locales.ts";
import React, {useEffect} from "react";
import {getUserCurrency} from "../../../../utilites/currency.ts";
import {IconLock, IconMail} from "@tabler/icons-react";
import { getConfig } from "../../../../utilites/config.ts";

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
        },
        validate: {
            password: hasLength({min: 8}, t`Password must be at least 8 characters`),
            password_confirmation: matchesField('password', t`Passwords are not the same`),
            email: isEmail(t`Please check your email is valid`),
        },
    });
    const errorHandler = useFormErrorResponseHandler();
    const mutate = useRegisterAccount();

    const registerUser = (data: RegisterAccountRequest) => {
        mutate.mutate({registerData: data}, {
            onSuccess: () => {
                navigate('/welcome');
            },
            onError: (error: any) => {
                errorHandler(form, error, error.response?.data?.message);
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
                <h2>{t`Welcome to ${getConfig("VITE_APP_NAME", "Hi.Events")} 👋`}</h2>
                <p>
                    <Trans>
                        Create an account or <NavLink to={'/auth/login'}>
                        {t`Log in`}
                    </NavLink> to get started
                    </Trans>
                </p>
            </header>

            <div className={classes.registerCard}>
                <form onSubmit={form.onSubmit((values) => registerUser(values as RegisterAccountRequest))}>

                    <SimpleGrid verticalSpacing={0} cols={{base: 2, xs: 2}}>
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

                    <div style={{marginBottom: '20px'}}>
                        <SimpleGrid verticalSpacing={0} cols={{base: 2, xs: 2}}>
                            <PasswordInput
                                {...form.getInputProps('password')}
                                label={t`Password`}
                                placeholder={t`Your password`}
                                required
                                mt="md"
                                mb={0}
                            />
                            <PasswordInput
                                {...form.getInputProps('password_confirmation')}
                                label={t`Confirm Password`}
                                placeholder={t`Confirm password`}
                                required
                                mt="md"
                                mb={0}
                            />
                        </SimpleGrid>
                    </div>

                    <TextInput
                        style={{display: 'none'}}
                        {...form.getInputProps('timezone')}
                        type="hidden"
                    />
                    <Button color={'var(--hi-pink)'} type="submit" fullWidth disabled={mutate.isPending}>
                        {mutate.isPending ? t`Working...` : t`Register`}
                    </Button>
                </form>
                <footer>
                    <Trans>
                        By registering you agree to our <NavLink target={'_blank'}
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
