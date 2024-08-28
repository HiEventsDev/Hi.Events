import {Button, TextInput,} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useMutation} from "@tanstack/react-query";
import {showError} from "../../../../utilites/notifications.tsx";
import {authClient} from "../../../../api/auth.client.ts";
import {useState} from "react";
import {NavLink} from "react-router-dom";
import {Card} from "../../../common/Card";

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
            showError('Something went wrong, please try again, or contact support if the problem persists');
        }
    });

    return (
        <Card>
            {showSuccessMessage && (
                <div>
                    <p>
                        If you have an account with us, you will receive an email with instructions on how to reset your
                        password.
                    </p>
                    <p>
                        <NavLink to={'/auth/login'}>Back to login</NavLink>
                    </p>
                </div>
            )}
            {!showSuccessMessage && (
                <>
                    <h3>Reset your password</h3>
                    <form onSubmit={form.onSubmit((values) => mutate.mutate(values.email))}>
                        <TextInput type={'email'} {...form.getInputProps('email')} label="Your email"
                                   placeholder="joe@bloggs.com" required/>
                        <Button fullWidth type="submit" disabled={mutate.isPending}>
                            {mutate.isPending ? 'Working...' : 'Reset password'}
                        </Button>
                    </form>
                    <footer>
                        <NavLink to={'/auth/login'}>Back to login</NavLink>
                    </footer>
                </>
            )}
        </Card>
    )
}

export default ForgotPassword;
