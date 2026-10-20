import {useEffect, useRef, useState} from "react";
import {Button, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {t} from "@lingui/macro";
import {accountSsoClient} from "../../api/accountSso.client.ts";
import {useStartSso} from "../../../../mutations/useSsoMutations.ts";
import {ssoError} from "../../../../utilites/ssoErrors.ts";
import {Callout} from "../../../../components/common/Callout";
import classes from "../../../../components/common/SsoButtons/SsoButtons.module.scss";

interface SsoDiscoveryProps {
    initialEmail?: string;
    returnPath?: string;
    onBack: () => void;
}

export const SsoDiscovery = ({initialEmail = '', returnPath, onBack}: SsoDiscoveryProps) => {
    const headingRef = useRef<HTMLHeadingElement>(null);
    const startSso = useStartSso();
    const [error, setError] = useState<{ message: string, notSetUp: boolean } | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const form = useForm({initialValues: {email: initialEmail}});

    useEffect(() => {
        headingRef.current?.focus();

        const resetOnReturn = (event: PageTransitionEvent) => {
            if (event.persisted) {
                setSubmitting(false);
            }
        };

        window.addEventListener('pageshow', resetOnReturn);

        return () => window.removeEventListener('pageshow', resetOnReturn);
    }, []);

    const submit = async ({email}: { email: string }) => {
        setError(null);
        setSubmitting(true);

        try {
            const provider = await accountSsoClient.discover(email.trim());
            await startSso.mutateAsync({provider, request: {intent: 'LOGIN', login_hint: email.trim(), return_path: returnPath}});
        } catch (caught) {
            const ssoFailure = ssoError(caught);
            setSubmitting(false);
            setError({
                message: ssoFailure.error_code === 'SSO_NOT_SET_UP'
                    ? t`Single sign-on isn't set up for this email. Check the address, or sign in another way.`
                    : ssoFailure.message,
                notSetUp: ssoFailure.error_code === 'SSO_NOT_SET_UP',
            });
        }
    };

    return (
        <div className={classes.discovery}>
            <h2 ref={headingRef} tabIndex={-1}>{t`Sign in with SSO`}</h2>
            <p>{t`Enter your work email and we'll take you to your organization's sign-in page.`}</p>
            {error && <Callout variant={error.notSetUp ? 'warning' : 'danger'}>{error.message}</Callout>}
            <form onSubmit={form.onSubmit(submit)}>
                <TextInput
                    {...form.getInputProps('email')}
                    label={t`Work email`}
                    placeholder="you@company.com"
                    type="email"
                    autoComplete="email"
                    data-autofocus
                    required
                />
                <Button type="submit" color="secondary.5" fullWidth mt="md" loading={submitting} data-testid="sso-discovery-submit">
                    {t`Continue`}
                </Button>
            </form>
            <button type="button" className={classes.backLink} onClick={onBack}>{t`Back to sign in`}</button>
        </div>
    );
};

export const EnterpriseSsoButton = ({onClick, disabled}: { onClick: () => void, disabled?: boolean }) => (
    <button type="button" className={classes.enterpriseButton} onClick={onClick} disabled={disabled} data-testid="sso-enterprise-button">
        {t`Sign in with SSO`}
    </button>
);
