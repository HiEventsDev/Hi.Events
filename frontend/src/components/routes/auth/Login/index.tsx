import {Button, PasswordInput, TextInput} from "@mantine/core";
import {NavLink} from "react-router-dom";
import {useMutation} from "@tanstack/react-query";
import {notifications} from '@mantine/notifications';
import {authClient} from "../../../../api/auth.client.ts";
import {LoginData, LoginResponse} from "../../../../types.ts";
import {useForm} from "@mantine/form";
import {redirectToPreviousUrl} from "../../../../api/client.ts";
import {Card} from "../../../common/Card";
import classes from "./Login.module.scss";
import {t, Trans} from "@lingui/macro";
import {useEffect, useState} from "react";
import {ChooseAccountModal} from "../../../modals/ChooseAccountModal";

const Login = () => {
    const form = useForm({
        initialValues: {
            email: '',
            password: '',
            account_id: '',
        }
    });
    const [showChooseAccount, setShowChooseAccount] = useState(false);

    const {mutate: loginUser, isLoading, data} = useMutation(
        (userData: LoginData) => authClient.login(userData),
        {
            onSuccess: (response: LoginResponse) => {
                if (response.token) {
                    redirectToPreviousUrl();
                    return;
                }

                if (response.accounts.length > 1) {
                    setShowChooseAccount(true);
                    return;
                }
            },
            onError: () => {
                notifications.show({
                    message: t`Please check your email and password and try again`,
                    color: 'red',
                });
            },
        }
    );

    useEffect(() => {
        form.values.account_id && loginUser(form.values);
    }, [form.values.account_id]);

    return (
        <>
            <Card>
                <form onSubmit={form.onSubmit((values) => loginUser(values))}>
                    <TextInput {...form.getInputProps('email')} label={t`Email`}
                               placeholder="hello@hi.events" required/>
                    <PasswordInput {...form.getInputProps('password')} label={t`Password`}
                                   placeholder={t`Your password`} required mt="md"/>
                    <p>
                        <NavLink to={`/auth/forgot-password`}>
                            {t`Forgot password?`}
                        </NavLink>
                    </p>
                    <Button type="submit" fullWidth loading={isLoading} disabled={isLoading}>
                        {isLoading ? t`Logging in` : t`Log in`}
                    </Button>
                </form>
            </Card>
            <div className={classes.registerMessage}>
                <Trans>
                    Don't have an account? {'  '}
                    <NavLink to={'/auth/register'}>
                        Sign Up
                    </NavLink>
                </Trans>
            </div>
            {(showChooseAccount && data) && <ChooseAccountModal onAccountChosen={(accountId) => {
                form.setFieldValue('account_id', accountId as string);
            }
            } accounts={data.accounts}/>}
        </>
    )
}

export default Login;
