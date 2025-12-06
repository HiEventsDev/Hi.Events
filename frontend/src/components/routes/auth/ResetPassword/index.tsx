import {Button, LoadingOverlay, PasswordInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {NavLink, useNavigate, useParams} from "react-router";
import {useResetPassword} from "../../../../mutations/useResetPassword.ts";
import {useVerifyPasswordResetToken} from "../../../../queries/useVerifyPasswordResetToken.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {useEffect} from "react";
import {ResetPasswordRequest} from "../../../../types.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {t} from "@lingui/macro";
import classes from "./ResetPassword.module.scss";

export const ResetPassword = () => {
    const form = useForm({
        initialValues: {
            password: '',
            password_confirmation: '',
        },
    });
    const {token} = useParams();
    const navigate = useNavigate();
    const mutate = useResetPassword();
    const verifyQuery = useVerifyPasswordResetToken(String(token));
    const errorHandler = useFormErrorResponseHandler();

    useEffect(() => {
        if (verifyQuery.isError) {
            showError(t`This reset password link is invalid or expired.`);
            navigate('/auth/login');
        }
    }, [verifyQuery.isError]);

    if (verifyQuery.isLoading) {
        return (
            <div className={classes.loadingWrapper}>
                <LoadingOverlay visible />
            </div>
        );
    }

    const handleSubmit = (values: ResetPasswordRequest) => mutate.mutate({
        token: String(token),
        resetData: values,
    }, {
        onError: (error) => {
            errorHandler(form, error);
        },
        onSuccess: () => {
            showSuccess(t`Password reset successfully. Please login with your new password.`);
            navigate('/auth/login');
        },
    });

    return (
        <>
            <header className={classes.header}>
                <h2>{t`Create new password`}</h2>
                <p>{t`Your new password must be at least 8 characters long.`}</p>
            </header>
            <div className={classes.resetPasswordCard}>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <PasswordInput
                        {...form.getInputProps('password')}
                        label={t`New Password`}
                        placeholder={t`Enter new password`}
                        required
                    />
                    <PasswordInput
                        {...form.getInputProps('password_confirmation')}
                        label={t`Confirm Password`}
                        placeholder={t`Confirm new password`}
                        required
                    />
                    <Button color="secondary.5" type="submit" fullWidth loading={mutate.isPending} disabled={mutate.isPending}>
                        {mutate.isPending ? t`Resetting...` : t`Reset password`}
                    </Button>
                </form>
                <footer>
                    <NavLink to={'/auth/login'}>{t`Back to login`}</NavLink>
                </footer>
            </div>
        </>
    );
}

export default ResetPassword;
