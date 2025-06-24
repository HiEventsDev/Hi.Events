import {Button, LoadingOverlay, PasswordInput,} from "@mantine/core";
import {useForm} from "@mantine/form";
import {NavLink, useNavigate, useParams} from "react-router";
import {useResetPassword} from "../../../../mutations/useResetPassword.ts";
import {useVerifyPasswordResetToken} from "../../../../queries/useVerifyPasswordResetToken.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {useEffect} from "react";
import {ResetPasswordRequest} from "../../../../types.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {t} from "@lingui/macro";
import {Card} from "../../../common/Card";
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
        return <LoadingOverlay visible/>
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
                <h2>{t`Reset Password`}</h2>
                <p>{t`Please enter your new password`}</p>
            </header>
            <Card>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <PasswordInput {...form.getInputProps('password')} label={t`New Password`} required/>
                    <PasswordInput {...form.getInputProps('password_confirmation')} label={t`Confirm Password`} required/>
                    <Button color={'var(--hi-pink)'} type="submit" fullWidth disabled={mutate.isPending}>
                        {mutate.isPending ? t`Working...` : t`Reset password`}
                    </Button>
                </form>
                <footer>
                    <NavLink to={'/auth/login'}>{t`Back to login`}</NavLink>
                </footer>
            </Card>
        </>

    )
}

export default ResetPassword;
