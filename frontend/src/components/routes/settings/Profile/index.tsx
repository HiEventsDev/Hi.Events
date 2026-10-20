import {useEffect, useState} from "react";
import {Anchor, Button, Select, Switch, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {t, Trans} from "@lingui/macro";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {useUpdateMe} from "../../../../mutations/useUpdateMe.ts";
import {useCancelEmailChange} from "../../../../mutations/useCancelEmailChange.ts";
import {useResendEmailConfirmation} from "../../../../mutations/useResendEmailConfirmation.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {timezones} from "../../../../../data/timezones.ts";
import {localeToFlagEmojiMap, localeToNameMap, SupportedLocales} from "../../../../locales.ts";
import {getConfig} from "../../../../utilites/config.ts";
import {Callout} from "../../../common/Callout";
import {ConfirmIdentityModal} from "../../../common/ConfirmIdentity/ConfirmIdentityModal.tsx";
import {IdentityConfirmation} from "../../../../types.ts";
import {resumeUrl, useResumeAction} from "../../../../hooks/useResumeAction.ts";
import {readSessionValue, writeSessionValue} from "../../../../utilites/ssoFlow.ts";
import {StatusDot} from "../../../common/StatusDot";
import {SettingsPage, SettingsRow, SettingsSaveFooter, SettingsSection} from "../../../common/SettingsPage";
import classes from "./Profile.module.scss";

const localeOptions = Object.keys(localeToNameMap).map(locale => ({
    value: locale,
    label: `${localeToFlagEmojiMap[locale as SupportedLocales]} ${localeToNameMap[locale as SupportedLocales]}`,
}));

const EMAIL_CHANGE_ACTION = 'email-change';
const PENDING_EMAIL_KEY = 'hi_pending_email_change';

const readPendingEmailChange = () => readSessionValue(PENDING_EMAIL_KEY);

const writePendingEmailChange = (email: string | null) => writeSessionValue(PENDING_EMAIL_KEY, email);

const Profile = () => {
    const {data: me} = useGetMe();
    const updateMutation = useUpdateMe();
    const cancelEmailChangeMutation = useCancelEmailChange();
    const resendConfirmationMutation = useResendEmailConfirmation();
    const errorHandler = useFormErrorResponseHandler();
    const [confirmationResent, setConfirmationResent] = useState(false);
    const canViewFinancials = useAccess().can('VIEW_FINANCIALS');
    const resumeAction = useResumeAction();
    const [confirmingEmailChange, setConfirmingEmailChange] = useState(false);

    const form = useForm({
        mode: 'controlled',
        initialValues: {
            first_name: '',
            last_name: '',
            email: '',
            timezone: '',
            locale: '' as string,
            marketing_opt_in: false,
            notify_new_orders: false,
        },
    });

    useEffect(() => {
        if (!me || form.isDirty()) {
            return;
        }
        const values = {
            first_name: me.first_name ?? '',
            last_name: me.last_name ?? '',
            email: me.email ?? '',
            timezone: me.timezone ?? '',
            locale: me.locale ?? '',
            marketing_opt_in: !!me.marketing_opted_in_at,
            notify_new_orders: !!me.notify_new_orders,
        };
        form.setInitialValues(values);
        form.setValues(values);
    }, [me]);

    useEffect(() => {
        const pendingEmail = readPendingEmailChange();

        if (resumeAction === EMAIL_CHANGE_ACTION && pendingEmail && me) {
            form.setFieldValue('email', pendingEmail);
            setConfirmingEmailChange(true);
        }
    }, [resumeAction, me?.id]);

    const save = (values: typeof form.values, confirmation?: IdentityConfirmation, setConfirmationError?: (message: string | null) => void) => {
        const localeChanged = form.isDirty('locale');

        updateMutation.mutate({
            userData: {
                ...values,
                confirmation_password: confirmation?.password,
                reauth_proof: confirmation?.reauth_proof,
            },
        }, {
            onSuccess: () => {
                writePendingEmailChange(null);
                setConfirmingEmailChange(false);
                form.resetDirty();
                showSuccess(t`Profile updated successfully`);

                if (localeChanged) {
                    document.cookie = `locale=${values.locale};path=/;max-age=31536000`;
                    window.location.reload();
                }
            },
            onError: (error: any) => {
                const confirmationError = error?.response?.data?.errors?.confirmation_password?.[0];

                if (confirmationError && setConfirmationError) {
                    setConfirmationError(confirmationError);
                    return;
                }

                setConfirmingEmailChange(false);
                errorHandler(form, error);
            },
        });
    };

    const handleSubmit = (values: typeof form.values) => {
        if (values.email.trim().toLowerCase() !== (me?.email ?? '').toLowerCase()) {
            writePendingEmailChange(values.email);
            setConfirmingEmailChange(true);
            return;
        }

        save(values);
    };

    const handleCancelEmailChange = () => {
        cancelEmailChangeMutation.mutate({userId: me?.id}, {
            onSuccess: () => showSuccess(t`Email change cancelled successfully`),
            onError: () => showError(t`Something went wrong. Please try again.`),
        });
    };

    const handleResendConfirmation = () => {
        resendConfirmationMutation.mutate({userId: me?.id}, {
            onSuccess: () => {
                showSuccess(t`Email confirmation resent successfully`);
                setConfirmationResent(true);
            },
            onError: () => showError(t`Something went wrong. Please try again.`),
        });
    };

    const renderEmailStatus = () => {
        if (!me || me.is_email_verified) {
            return null;
        }

        if (confirmationResent) {
            return <span className={classes.emailStatus}>{t`Please check your email to confirm your email address`}</span>;
        }

        return (
            <span className={classes.emailStatus}>
                <StatusDot tone="warning" strong>{t`Email not verified`}</StatusDot>
                <Anchor
                    component="button"
                    type="button"
                    size="sm"
                    onClick={handleResendConfirmation}
                    disabled={resendConfirmationMutation.isPending}
                >
                    {t`Resend email confirmation`}
                </Anchor>
            </span>
        );
    };

    return (
        <SettingsPage
            title={t`Profile`}
            description={t`Your name, sign-in email and preferences.`}
        >
            {me?.has_pending_email_change && (
                <Callout variant="info" title={t`Email change pending`}>
                    <p className={classes.calloutText}>
                        <Trans>Your email request change to <b>{me.pending_email}</b> is pending. Please check your email to confirm</Trans>
                    </p>
                    <p className={classes.calloutText}>
                        {t`If you did not request this change, please immediately change your password.`}
                    </p>
                    <Button
                        size="xs"
                        variant="default"
                        onClick={handleCancelEmailChange}
                        loading={cancelEmailChangeMutation.isPending}
                    >
                        {t`Cancel email change`}
                    </Button>
                </Callout>
            )}

            <SettingsSection>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <fieldset disabled={!me || updateMutation.isPending}>
                        <SettingsRow label={t`Name`} htmlFor="profile-first-name">
                            <div className={classes.nameFields}>
                                <TextInput
                                    id="profile-first-name"
                                    aria-label={t`First name`}
                                    placeholder={t`First name`}
                                    autoComplete="given-name"
                                    required
                                    {...form.getInputProps('first_name')}
                                />
                                <TextInput
                                    aria-label={t`Last name`}
                                    placeholder={t`Last name`}
                                    autoComplete="family-name"
                                    required
                                    {...form.getInputProps('last_name')}
                                />
                            </div>
                        </SettingsRow>
                        <SettingsRow
                            label={t`Email`}
                            description={renderEmailStatus() ?? t`Used to sign in. Changing it sends a confirmation link to the new address.`}
                            htmlFor="profile-email"
                        >
                            <TextInput
                                id="profile-email"
                                type="email"
                                autoComplete="email"
                                required
                                {...form.getInputProps('email')}
                            />
                        </SettingsRow>
                        <SettingsRow label={t`Language`} htmlFor="profile-locale">
                            <Select
                                id="profile-locale"
                                data={localeOptions}
                                allowDeselect={false}
                                {...form.getInputProps('locale')}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`Timezone`}
                            htmlFor="profile-timezone"
                        >
                            <Select
                                id="profile-timezone"
                                searchable
                                data={timezones}
                                allowDeselect={false}
                                {...form.getInputProps('timezone')}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`Product updates`}
                            description={<Trans>Receive product updates from {getConfig("VITE_APP_NAME", "Hi.Events")}.</Trans>}
                            htmlFor="profile-marketing"
                            inlineControl
                        >
                            <Switch
                                id="profile-marketing"
                                {...form.getInputProps('marketing_opt_in', {type: 'checkbox'})}
                            />
                        </SettingsRow>
                        {canViewFinancials && (
                            <SettingsRow
                                label={t`New order emails`}
                                description={t`Email me about new orders on the events I can access`}
                                htmlFor="profile-notify-new-orders"
                                inlineControl
                            >
                                <Switch
                                    id="profile-notify-new-orders"
                                    data-testid="profile-notify-new-orders"
                                    {...form.getInputProps('notify_new_orders', {type: 'checkbox'})}
                                />
                            </SettingsRow>
                        )}
                    </fieldset>
                    <SettingsSaveFooter
                        isDirty={form.isDirty()}
                        isSaving={updateMutation.isPending}
                        onDiscard={form.reset}
                        saveTestId="profile-save-button"
                    />
                </form>
            </SettingsSection>
            {confirmingEmailChange && (
                <ConfirmIdentityModal
                    heading={t`Confirm it's you`}
                    description={t`To change the email you sign in with, confirm it's you. We'll send a confirmation link to ${form.values.email}.`}
                    confirmLabel={t`Change email`}
                    resumeUrl={resumeUrl('/settings/profile', EMAIL_CHANGE_ACTION)}
                    isSubmitting={updateMutation.isPending}
                    onConfirm={(confirmation, setError) => save(form.values, confirmation, setError)}
                    onClose={() => {
                        writePendingEmailChange(null);
                        setConfirmingEmailChange(false);
                    }}
                />
            )}
        </SettingsPage>
    );
};

export default Profile;
