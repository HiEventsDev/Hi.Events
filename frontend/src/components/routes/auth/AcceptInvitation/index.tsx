import {Anchor, Button, Checkbox, Loader, PasswordInput, Select, TextInput} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {timezones} from "../../../../../data/timezones.ts";
import {useNavigate, useParams} from "react-router";
import {hasLength, matchesField, useForm} from "@mantine/form";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetInvitation} from "../../../../queries/useGetInvitation.ts";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {useEffect, useState} from "react";
import {useAcceptInvitation} from "../../../../mutations/useAcceptInvitation.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {AcceptInvitationRequest} from "../../../../types.ts";
import {getConfig} from "../../../../utilites/config.ts";
import {authClient} from "../../../../api/auth.client.ts";
import {setPreviousUrl} from "../../../../api/client.ts";
import {Callout} from "../../../common/Callout";
import {SsoSection, useSsoOptions} from "../../../common/SsoButtons/SsoSection.tsx";
import {useJoinInvitation} from "../../../../mutations/useSsoMutations.ts";
import classes from "./AcceptInvitation.module.scss";

const detectedTimezone = () => typeof window !== 'undefined'
    ? Intl.DateTimeFormat().resolvedOptions().timeZone
    : 'UTC';

const AcceptInvitation = () => {
    const navigate = useNavigate();
    const {token} = useParams();
    const invitationPath = `/auth/accept-invitation/${token}`;
    const [error, setError] = useState<string | null>(null);
    const form = useForm({
        initialValues: {
            first_name: '',
            last_name: '',
            password: '',
            timezone: '',
            password_confirmation: '',
            terms: false,
            marketing_opt_in: false,
        },
        validate: {
            first_name: hasLength({min: 1, max: 50}, t`First name must be between 1 and 50 characters`),
            password: hasLength({min: 8}, t`Password must be a minimum  of 8 characters`),
            password_confirmation: matchesField('password', t`Passwords are not the same`),
            terms: (value) => value === true ? null : t`You must agree to the terms and conditions`,
        },
    });
    const {data: invitationResponse, isFetched, isError, error: invitationError, refetch} = useGetInvitation(String(token));
    const invitation = invitationResponse?.data;
    const me = useGetMe();
    const {passwordLoginEnabled, showSso} = useSsoOptions();
    const errorHandler = useFormErrorResponseHandler();
    const acceptInvitationMutation = useAcceptInvitation();
    const joinInvitationMutation = useJoinInvitation();
    const inviteSsoRequest = {intent: 'INVITE' as const, invitation_token: token, timezone: detectedTimezone()};

    useEffect(() => {
        if (!isError) {
            return;
        }

        const message = (invitationError as any)?.response?.data?.message || t`Something went wrong`;
        navigate('/auth/login', {replace: true, state: {notice: message}});
    }, [isError]);

    useEffect(() => {
        if (!invitation) {
            return;
        }

        form.setValues({
            first_name: invitation.first_name,
            last_name: invitation.last_name ?? '',
            timezone: invitation.timezone || detectedTimezone(),
        });
    }, [isFetched]);

    const handleSubmit = (values: AcceptInvitationRequest) => {
        setError(null);
        acceptInvitationMutation.mutate({
            userData: values,
            token: String(token),
        }, {
            onSuccess: () => {
                showSuccess(t`Welcome aboard! Sign in to continue.`);
                navigate('/auth/login', {state: {email: invitation?.email}});
            },
            onError: (requestError: any) => {
                if (['INVITATION_SIGN_IN_REQUIRED', 'SSO_REQUIRED'].includes(requestError?.response?.data?.error_code)) {
                    refetch();
                    return;
                }

                if (requestError?.response?.data?.errors) {
                    errorHandler(form, requestError);
                    return;
                }

                setError(requestError?.response?.data?.message ?? t`Something went wrong. Please try again.`);
            },
        });
    };

    const join = () => {
        setError(null);
        joinInvitationMutation.mutate(String(token), {
            onSuccess: () => window.location.replace('/manage/events'),
            onError: (requestError: any) => setError(requestError?.response?.data?.message ?? t`Something went wrong. Please try again.`),
        });
    };

    const signOut = async () => {
        await authClient.logout();
        window.location.href = invitationPath;
    };

    if (!invitation) {
        return <Loader size="sm"/>;
    }

    const heading = (
        <header className={classes.header}>
            <h2>{t`Join ${invitation.account_name}`}</h2>
            <p><Trans>This invitation was sent to <span className={classes.invitedEmail}>{invitation.email}</span>.</Trans></p>
        </header>
    );

    const errorCallout = error && (
        <Callout variant="danger" onDismiss={() => setError(null)}>{error}</Callout>
    );

    if (invitation.requires_sign_in && me.isSuccess) {
        const isInvitedUser = me.data.email?.toLowerCase() === invitation.email.toLowerCase();

        return (
            <>
                {heading}
                {errorCallout}
                {!isInvitedUser && (
                    <Callout variant="warning" title={t`You're signed in as ${me.data.email}`}>
                        {t`Sign out, then sign in as ${invitation.email} to accept this invitation.`}
                    </Callout>
                )}
                <div className={classes.signedIn}>
                    {isInvitedUser ? (
                        <Button color="secondary.5" fullWidth loading={joinInvitationMutation.isPending} onClick={join} data-testid="invitation-join-button">
                            {t`Join ${invitation.account_name}`}
                        </Button>
                    ) : (
                        <Button color="secondary.5" fullWidth onClick={signOut} data-testid="invitation-sign-out-button">
                            {t`Sign out`}
                        </Button>
                    )}
                </div>
            </>
        );
    }

    if (invitation.sso_required) {
        return (
            <>
                {heading}
                <Callout variant="info" title={t`Your organization requires single sign-on`}>
                    {t`Sign in through your organization's identity provider to join ${invitation.account_name}.`}
                </Callout>
                <div className={classes.signedIn}>
                    <Button
                        color="secondary.5"
                        fullWidth
                        onClick={() => navigate('/auth/login', {state: {ssoStep: true, email: invitation.email}})}
                        data-testid="invitation-sso-button"
                    >
                        {t`Sign in with SSO`}
                    </Button>
                </div>
            </>
        );
    }

    if (invitation.requires_sign_in) {
        return (
            <>
                {heading}
                {errorCallout}
                <Callout variant="info" title={t`Sign in to join`}>
                    {t`You already have an account. Sign in and we'll bring you back here to join the team.`}
                </Callout>
                <SsoSection request={inviteSsoRequest} onError={setError}/>
                {passwordLoginEnabled && (
                    <div className={classes.signedIn}>
                        <Button
                            color="secondary.5"
                            fullWidth
                            onClick={() => {
                                setPreviousUrl(invitationPath);
                                navigate('/auth/login', {state: {email: invitation.email}});
                            }}
                        >
                            {t`Sign in with password`}
                        </Button>
                    </div>
                )}
            </>
        );
    }

    return (
        <>
            {heading}
            {errorCallout}
            <SsoSection request={inviteSsoRequest} onError={setError} disabled={acceptInvitationMutation.isPending}/>
            {showSso && !passwordLoginEnabled && (
                <p className={classes.terms}>
                    <Trans>
                        By continuing you agree to our <Anchor target="_blank" href={getConfig("VITE_TOS_URL", "https://hi.events/terms-of-service") as string}>Terms of Service</Anchor> and <Anchor target="_blank" href={getConfig("VITE_PRIVACY_URL", "https://hi.events/privacy-policy") as string}>Privacy Policy</Anchor>.
                    </Trans>
                </p>
            )}
            {passwordLoginEnabled && (
                <div className={classes.invitationCard}>
                    <form onSubmit={form.onSubmit(handleSubmit)}>
                        <fieldset>
                            <div className={classes.inputGroup}>
                                <TextInput
                                    required
                                    {...form.getInputProps('first_name')}
                                    label={t`First Name`}
                                    placeholder={t`John`}
                                    autoComplete="given-name"
                                />
                                <TextInput
                                    {...form.getInputProps('last_name')}
                                    label={t`Last Name`}
                                    placeholder={t`Smith`}
                                    autoComplete="family-name"
                                />
                            </div>

                            <Select
                                required
                                searchable
                                data={timezones}
                                {...form.getInputProps('timezone')}
                                label={t`Timezone`}
                                placeholder={t`Select timezone`}
                            />

                            <div className={classes.inputGroup}>
                                <PasswordInput
                                    {...form.getInputProps('password')}
                                    label={t`Password`}
                                    placeholder={t`Create a password`}
                                    autoComplete="new-password"
                                    required
                                />
                                <PasswordInput
                                    {...form.getInputProps('password_confirmation')}
                                    label={t`Confirm Password`}
                                    placeholder={t`Confirm password`}
                                    autoComplete="new-password"
                                    required
                                />
                            </div>

                            <Checkbox
                                {...form.getInputProps('terms', {type: 'checkbox'})}
                                label={(
                                    <Trans>
                                        I agree to the{' '}
                                        <Anchor
                                            target={'_blank'}
                                            href={getConfig("VITE_TOS_URL", 'https://hi.events/terms-of-service')}
                                        >
                                            terms and conditions
                                        </Anchor>
                                    </Trans>
                                )}
                            />

                            <Checkbox
                                mb="md"
                                {...form.getInputProps('marketing_opt_in', {type: 'checkbox'})}
                                label={<Trans>Receive product updates from {getConfig("VITE_APP_NAME", "Hi.Events")}.</Trans>}
                            />

                            <Button
                                color="secondary.5"
                                fullWidth
                                loading={acceptInvitationMutation.isPending}
                                type="submit"
                                data-testid="invitation-accept-button"
                            >
                                {t`Join ${invitation.account_name}`}
                            </Button>
                        </fieldset>
                    </form>
                </div>
            )}
        </>
    );
};

export default AcceptInvitation;
