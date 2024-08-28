import {Button, PasswordInput, TextInput} from "@mantine/core";
import {hasLength, isEmail, matchesField, useForm} from "@mantine/form";
import {RegisterAccountRequest} from "../../../../types.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {useRegisterAccount} from "../../../../mutations/useRegisterAccount.ts";
import {NavLink, useNavigate} from "react-router-dom";
import {t, Trans} from "@lingui/macro";
import {InputGroup} from "../../../common/InputGroup";
import {Card} from "../../../common/Card";
import classes from "./Register.module.scss";
import {getClientLocale} from "../../../../locales.ts";

export const Register = () => {
    const navigate = useNavigate();
    const form = useForm({
        validateInputOnBlur: true,
        initialValues: {
            first_name: '',
            last_name: '',
            email: '',
            password: '',
            password_confirmation: '',
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            locale: getClientLocale(),
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

    return (
        <>
            <header className={classes.header}>
                <h2>{t`Begin selling tickets in minutes`}</h2>
                <p>
                    <Trans>
                        Create an account or <NavLink to={'/auth/login'}>
                        {t`Login`}
                    </NavLink> to get started
                    </Trans>
                </p>
            </header>

            <Card>
                <form onSubmit={form.onSubmit((values) => registerUser(values as RegisterAccountRequest))}>
                    <InputGroup>
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
                    </InputGroup>

                    <TextInput mb={0} {...form.getInputProps('email')} label={t`Email`} placeholder={'your@email.com'}
                               required/>

                    <InputGroup>
                        <PasswordInput {...form.getInputProps('password')}
                                       label={t`Password`}
                                       placeholder={t`Your password`}
                                       required mt="md"
                                       mb={10}
                        />
                        <PasswordInput {...form.getInputProps('password_confirmation')}
                                       label={t`Confirm Password`}
                                       placeholder={t`Confirm password`}
                                       required
                                       mt="md"
                                       mb={10}
                        />
                    </InputGroup>
                    <TextInput
                        style={{display: 'none'}}
                        {...form.getInputProps('timezone')}
                        type="hidden"
                    />
                    <Button type="submit" fullWidth disabled={mutate.isPending}>
                        {mutate.isPending ? t`Working...` : t`Register`}
                    </Button>
                </form>
                <footer>
                    <Trans>
                        By registering you agree to our <NavLink target={'_blank'}
                                                                 to={'https://hi.events/terms-of-service?utm_source=app-register-footer'}>Terms
                        of Service</NavLink> and <NavLink
                        target={'_blank'}
                        to={'https://hi.events/privacy-policy?utm_source=app-register-footer'}>Privacy Policy</NavLink>.
                    </Trans>
                </footer>
            </Card>
        </>
    )
}

export default Register;
