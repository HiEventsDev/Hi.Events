import {Button, PasswordInput, TextInput, Collapse, UnstyledButton} from "@mantine/core";
import {NavLink, useLocation} from "react-router";
import {useMutation} from "@tanstack/react-query";
import {notifications} from '@mantine/notifications';
import {authClient} from "../../../../api/auth.client.ts";
import {LoginData, LoginResponse} from "../../../../types.ts";
import {useForm} from "@mantine/form";
import {redirectToPreviousUrl} from "../../../../api/client.ts";
import classes from "./Login.module.scss";
import {t, Trans} from "@lingui/macro";
import {useEffect, useState} from "react";
import {ChooseAccountModal} from "../../../modals/ChooseAccountModal";
import {useSendTicketLookupEmail} from "../../../../mutations/useSendTicketLookupEmail.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import {IconTicket, IconChevronDown} from "@tabler/icons-react";

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
    const [ticketLookupOpen, setTicketLookupOpen] = useState(false);

    const ticketLookupForm = useForm({
        initialValues: {
            email: '',
        }
    });
    const [ticketLookupSuccess, setTicketLookupSuccess] = useState(false);

    const {mutate: loginUser, isPending, data} = useMutation({
        mutationFn: (userData: LoginData) => authClient.login(userData),

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
                position: 'top-center',
            });
        }
    });

    const ticketLookupMutation = useSendTicketLookupEmail();

    useEffect(() => {
        form.values.account_id && loginUser(form.values);
    }, [form.values.account_id]);

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
                               placeholder="hello@example.com"
                               required
                    />
                    <PasswordInput {...form.getInputProps('password')}
                                   label={t`Password`}
                                   placeholder={t`Your password`}
                                   required
                                   mt="md"
                    />
                    <Button color="secondary.5" type="submit" fullWidth loading={isPending} disabled={isPending} mt="lg">
                        {isPending ? t`Logging in` : t`Log in`}
                    </Button>
                    <p>
                        <NavLink to={`/auth/forgot-password`}>
                            {t`Forgot password?`}
                        </NavLink>
                    </p>
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

                <Collapse in={ticketLookupOpen}>
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

            {(showChooseAccount && data) && <ChooseAccountModal onAccountChosen={(accountId) => {
                form.setFieldValue('account_id', accountId as string);
            }
            } accounts={data.accounts}/>}
        </>
    )
}

export default Login;
