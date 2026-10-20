import {Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconAlertTriangle, IconBuilding, IconClockHour4, IconMailForward, IconUserCheck} from "@tabler/icons-react";
import {ReactNode, useEffect, useRef} from "react";
import {SsoErrorResponse} from "../../../../types.ts";
import loginClasses from "../../auth/Login/Login.module.scss";
import classes from "./SsoCallback.module.scss";

export interface SsoErrorAction {
    label: string;
    onClick: () => void;
    testId?: string;
}

interface SsoErrorStateProps {
    error: SsoErrorResponse;
    providerName: string;
    passwordLoginEnabled: boolean;
    primary: SsoErrorAction;
    secondary?: SsoErrorAction;
}

interface ErrorCopy {
    title: string;
    body: string;
    icon: ReactNode;
    neutral?: boolean;
}

const errorCopy = (error: SsoErrorResponse, providerName: string, passwordLoginEnabled: boolean): ErrorCopy => {
    const warning = <IconAlertTriangle size={26} stroke={1.75}/>;
    const email = error.context?.email;

    switch (error.error_code) {
        case 'SSO_LINK_REQUIRES_LOGIN':
            return email
                ? {
                    title: t`You already have an account with ${email}`,
                    body: passwordLoginEnabled
                        ? t`Sign in with your password, then connect ${providerName} in your security settings.`
                        : t`Sign in with the account you usually use, or ask your administrator for help.`,
                    icon: <IconUserCheck size={26} stroke={1.75}/>,
                    neutral: true,
                }
                : {
                    title: t`We couldn't sign you in with ${providerName}`,
                    body: passwordLoginEnabled
                        ? t`Sign in with your email and password instead.`
                        : t`Sign in with the account you usually use, or ask your administrator for help.`,
                    icon: warning,
                };
        case 'SSO_REQUIRED':
            return {
                title: t`Your organization uses single sign-on`,
                body: email
                    ? t`Accounts for ${email} are managed by your organization. Sign in through its identity provider instead.`
                    : t`Sign in through your organization's identity provider instead.`,
                icon: <IconBuilding size={26} stroke={1.75}/>,
                neutral: true,
            };
        case 'SSO_INVITATION_PENDING':
            return {
                title: t`You've been invited`,
                body: t`Open the invitation email we sent you to finish joining the team.`,
                icon: <IconMailForward size={26} stroke={1.75}/>,
                neutral: true,
            };
        case 'SSO_NO_ACTIVE_ACCOUNT':
            return {
                title: t`Your account isn't active`,
                body: t`Ask the account owner to invite you again.`,
                icon: warning,
            };
        case 'SSO_REGISTRATION_DISABLED':
            return {
                title: t`No account found`,
                body: t`Ask your administrator to invite you.`,
                icon: warning,
            };
        case 'SSO_EMAIL_REQUIRED':
            return {
                title: t`${providerName} didn't share your email`,
                body: t`Allow access to your email address when you sign in, then try again.`,
                icon: warning,
            };
        case 'SSO_PROVIDER_UNAVAILABLE':
            return {
                title: t`${providerName} isn't responding`,
                body: t`This is usually temporary. Try again in a moment.`,
                icon: warning,
            };
        case 'SSO_STATE_INVALID':
        case 'SSO_TICKET_INVALID':
            return {
                title: t`This sign-in link has expired`,
                body: t`Sign-in links work once and expire after 10 minutes. Start again to continue.`,
                icon: <IconClockHour4 size={26} stroke={1.75}/>,
                neutral: true,
            };
        case 'SSO_FLOW_MISSING':
            return {
                title: t`This sign-in link has expired`,
                body: t`Sign-in links work once and expire after 10 minutes. Start again from the sign-in page.`,
                icon: <IconClockHour4 size={26} stroke={1.75}/>,
                neutral: true,
            };
        case 'SSO_IDENTITY_IN_USE':
            return {
                title: t`This ${providerName} account is already in use`,
                body: t`It's connected to another user. Use a different ${providerName} account.`,
                icon: warning,
            };
        case 'SSO_INVITE_EMAIL_MISMATCH':
            return {
                title: t`This invitation is for ${error.context?.invited_email ?? ''}`,
                body: t`You signed in as ${error.context?.signed_in_as ?? ''}. Use the account the invitation was sent to.`,
                icon: <IconMailForward size={26} stroke={1.75}/>,
                neutral: true,
            };
        case 'SSO_INVITE_SIGN_IN_REQUIRED':
            return {
                title: t`Sign in to accept the invitation`,
                body: t`You already have an account. Sign in, and we'll bring you back to the invitation.`,
                icon: <IconUserCheck size={26} stroke={1.75}/>,
                neutral: true,
            };
        case 'SSO_INVITE_INVALID':
            return {
                title: t`This invitation is no longer valid`,
                body: t`Ask the person who invited you to send a new invitation.`,
                icon: warning,
            };
        case 'SSO_REAUTH_FAILED':
            return {
                title: t`We couldn't confirm it's you`,
                body: t`Sign in with an account you've already connected, and make sure it's a fresh sign-in.`,
                icon: warning,
            };
        case 'SSO_EMAIL_TAKEN':
            return {
                title: t`You already have an account`,
                body: t`An account with this email already exists. Sign in instead.`,
                icon: <IconUserCheck size={26} stroke={1.75}/>,
                neutral: true,
            };
        case 'SSO_PROVIDER_NOT_CONFIGURED':
            return {
                title: t`This sign-in option isn't available`,
                body: t`Choose another way to sign in.`,
                icon: warning,
            };
        default:
            return {
                title: t`We couldn't sign you in`,
                body: error.message,
                icon: warning,
            };
    }
};

export const SsoErrorState = ({error, providerName, passwordLoginEnabled, primary, secondary}: SsoErrorStateProps) => {
    const headingRef = useRef<HTMLHeadingElement>(null);
    const copy = errorCopy(error, providerName, passwordLoginEnabled);

    useEffect(() => {
        headingRef.current?.focus();
    }, [error.error_code]);

    return (
        <div className={loginClasses.challenge} data-testid="sso-error" data-error-code={error.error_code}>
            <header className={loginClasses.challengeHeader}>
                <div className={copy.neutral ? loginClasses.challengeIcon : `${loginClasses.challengeIcon} ${loginClasses.challengeIconWarning}`}>{copy.icon}</div>
                <h2 ref={headingRef} tabIndex={-1}>{copy.title}</h2>
                <p>{copy.body}</p>
            </header>
            <div className={classes.actions}>
                <Button color="secondary.5" fullWidth onClick={primary.onClick} data-testid={primary.testId}>
                    {primary.label}
                </Button>
                {secondary && (
                    <Button variant="subtle" color="gray" fullWidth onClick={secondary.onClick} data-testid={secondary.testId}>
                        {secondary.label}
                    </Button>
                )}
            </div>
        </div>
    );
};
