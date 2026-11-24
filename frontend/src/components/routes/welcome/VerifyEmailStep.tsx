import {Button, PinInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {useEffect, useState} from "react";
import classes from "./Welcome.module.scss";
import {User} from "../../../types.ts";
import {useConfirmEmailWithCode} from "../../../mutations/useConfirmEmailWithCode.ts";
import {useResendEmailConfirmation} from "../../../mutations/useResendEmailConfirmation.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {AnalyticsEvents, trackEvent} from "../../../utilites/analytics.ts";
import {authClient} from "../../../api/auth.client.ts";

const CODE_LENGTH = 5;
const DEFAULT_RESEND_COOLDOWN_SECONDS = 30;

interface VerifyEmailStepProps {
    user: User;
    stepLabel: string;
}

export const VerifyEmailStep = ({user, stepLabel}: VerifyEmailStepProps) => {
    const confirmEmailMutation = useConfirmEmailWithCode();
    const resendMutation = useResendEmailConfirmation();
    const [code, setCode] = useState('');
    const [resendCooldown, setResendCooldown] = useState(0);
    const [isSigningOut, setIsSigningOut] = useState(false);

    useEffect(() => {
        if (resendCooldown <= 0) {
            return;
        }
        const timer = setTimeout(() => setResendCooldown(resendCooldown - 1), 1000);
        return () => clearTimeout(timer);
    }, [resendCooldown]);

    const verify = (value: string) => {
        if (value.length !== CODE_LENGTH || confirmEmailMutation.isPending) {
            return;
        }

        confirmEmailMutation.mutate({userId: user.id || '', code: value}, {
            onSuccess: () => {
                trackEvent(AnalyticsEvents.SIGNUP_COMPLETED);
                showSuccess(t`Email verified successfully!`);
            },
            onError: (error: any) => {
                showError(error.response?.data?.message || t`Failed to verify email`);
                setCode('');
            },
        });
    };

    const resend = async () => {
        if (!user.id) {
            return;
        }

        try {
            await resendMutation.mutateAsync({userId: user.id});
            showSuccess(t`A new verification code has been sent to your email`);
            setResendCooldown(DEFAULT_RESEND_COOLDOWN_SECONDS);
            setCode('');
        } catch (error: any) {
            if (error?.response?.status === 429) {
                const retryAfter = error.response.data?.message?.match(/\d+/)?.[0];
                setResendCooldown(retryAfter ? parseInt(retryAfter) : DEFAULT_RESEND_COOLDOWN_SECONDS);
                showError(error.response.data?.message || t`Please wait before requesting another code`);
                return;
            }
            showError(t`Failed to resend verification code`);
        }
    };

    const signOutToRegister = async () => {
        setIsSigningOut(true);
        try {
            await authClient.logout();
        } finally {
            window.location.href = '/auth/register';
        }
    };

    return (
        <>
            <header className={classes.header}>
                <p className={classes.step}>{stepLabel}</p>
                <h2>{t`Check your email`}</h2>
                <p>
                    {t`We've sent a 5-digit verification code to:`}{' '}
                    <span className={classes.email}>{user.email}</span>
                </p>
            </header>

            <form
                className={classes.form}
                onSubmit={(event) => {
                    event.preventDefault();
                    verify(code);
                }}
            >
                <PinInput
                    className={classes.pinInput}
                    value={code}
                    onChange={setCode}
                    onComplete={verify}
                    length={CODE_LENGTH}
                    type="number"
                    inputMode="numeric"
                    oneTimeCode
                    autoFocus
                    size="lg"
                    gap="sm"
                    placeholder=""
                    aria-label={t`Verification code`}
                    disabled={confirmEmailMutation.isPending}
                />

                <Button
                    type="submit"
                    color="secondary.5"
                    fullWidth
                    disabled={code.length !== CODE_LENGTH || confirmEmailMutation.isPending}
                >
                    {confirmEmailMutation.isPending ? t`Verifying...` : t`Verify Email`}
                </Button>
            </form>

            <p className={classes.note}>
                {t`The code will expire in 10 minutes. Check your spam folder if you don't see the email.`}
            </p>

            <div className={classes.links}>
                <button
                    type="button"
                    className={classes.linkButton}
                    onClick={resend}
                    disabled={resendCooldown > 0 || resendMutation.isPending}
                >
                    {resendCooldown > 0 ? t`Resend in ${resendCooldown}s` : t`Resend code`}
                </button>
                <button
                    type="button"
                    className={classes.linkButton}
                    onClick={signOutToRegister}
                    disabled={isSigningOut}
                    data-testid="welcome-use-different-email-button"
                >
                    {t`Use a different email`}
                </button>
            </div>
        </>
    );
};
