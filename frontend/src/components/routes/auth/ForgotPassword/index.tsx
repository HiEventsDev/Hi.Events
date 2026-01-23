import {Button, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useMutation} from "@tanstack/react-query";
import {showError} from "../../../../utilites/notifications.tsx";
import {authClient} from "../../../../api/auth.client.ts";
import {useState} from "react";
import {NavLink} from "react-router";
import {t} from "@lingui/macro";
import classes from "./ForgotPassword.module.scss";
import {IconArrowLeft, IconCheck} from "@tabler/icons-react";

export const ForgotPassword = () => {
    const form = useForm({
        initialValues: {
            email: '',
        },
    });
    const [showSuccessMessage, setShowSuccessMessage] = useState(false);

    const mutate = useMutation({
        mutationFn: (email: string) => {
            return authClient.forgotPassword({
                email: email,
            });
        },

        onSuccess: () => {
            setShowSuccessMessage(true);
        },

        onError: () => {
            showError(t`Something went wrong, please try again, or contact support if the problem persists`);
        }
    });

    if (showSuccessMessage) {
        return (
            <div className={classes.successMessage}>
                <div className={classes.successIcon}>
                    <IconCheck size={24} />
                </div>
                <h3>{t`Check your email`}</h3>
                <p>
                    {t`If you have an account with us, you will receive an email with instructions on how to reset your password.`}
                </p>
                <NavLink to={'/auth/login'}>
                    <IconArrowLeft size={14} />
                    {t`Back to login`}
                </NavLink>
            </div>
        );
    }

    return (
        <>
            <header className={classes.header}>
                <h2>{t`Reset password`}</h2>
                <p>{t`Enter your email and we'll send you instructions to reset your password.`}</p>
            </header>
            <div className={classes.forgotPasswordCard}>
                <form onSubmit={form.onSubmit((values) => mutate.mutate(values.email))}>
                    <TextInput
                        type="email"
                        {...form.getInputProps('email')}
                        label={t`Email`}
                        placeholder="you@example.com"
                        required
                    />
                    <Button color="secondary.5" type="submit" fullWidth loading={mutate.isPending} disabled={mutate.isPending}>
                        {mutate.isPending ? t`Sending...` : t`Send reset link`}
                    </Button>
                </form>
                <footer>
                    <NavLink to={'/auth/login'}>{t`Back to login`}</NavLink>
                </footer>
            </div>
        </>
    );
}

export default ForgotPassword;
