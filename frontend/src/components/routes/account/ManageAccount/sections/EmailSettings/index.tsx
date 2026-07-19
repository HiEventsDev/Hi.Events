import {Button, Checkbox, Select, TextInput, PasswordInput, Divider, Text} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useEffect} from "react";
import {t} from "@lingui/macro";
import {Card} from "../../../../../common/Card";
import {HeadingCard} from "../../../../../common/HeadingCard";
import {LoadingMask} from "../../../../../common/LoadingMask";
import {useGetAccount} from "../../../../../../queries/useGetAccount.ts";
import {useGetAccountEmailSettings} from "../../../../../../queries/useGetAccountEmailSettings.ts";
import {useUpsertAccountEmailSettings} from "../../../../../../mutations/useUpsertAccountEmailSettings.ts";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {AccountEmailSettings} from "../../../../../../types.ts";
import classes from "../../ManageAccount.module.scss";

const ENCRYPTION_OPTIONS = [
    {value: '', label: 'None'},
    {value: 'tls', label: 'TLS'},
    {value: 'ssl', label: 'SSL'},
    {value: 'starttls', label: 'STARTTLS'},
];

const EmailSettings = () => {
    const {data: account} = useGetAccount();
    const accountId = account?.id;

    const emailSettingsQuery = useGetAccountEmailSettings(accountId, {
        enabled: !!accountId,
    });

    const upsertMutation = useUpsertAccountEmailSettings(accountId);
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm<AccountEmailSettings>({
        initialValues: {
            smtp_enabled: false,
            smtp_host: '',
            smtp_port: undefined,
            smtp_encryption: '',
            smtp_username: '',
            smtp_password: '',
            mail_from_address: '',
            mail_from_name: '',
        },
    });

    useEffect(() => {
        if (emailSettingsQuery.data) {
            const s = emailSettingsQuery.data;
            form.setValues({
                smtp_enabled: s.smtp_enabled ?? false,
                smtp_host: s.smtp_host ?? '',
                smtp_port: s.smtp_port,
                smtp_encryption: s.smtp_encryption ?? '',
                smtp_username: s.smtp_username ?? '',
                // Never prefill the password – backend keeps the existing one if blank
                smtp_password: '',
                mail_from_address: s.mail_from_address ?? '',
                mail_from_name: s.mail_from_name ?? '',
            });
        }
    }, [emailSettingsQuery.isFetched]);

    const handleSubmit = (values: AccountEmailSettings) => {
        upsertMutation.mutate(values, {
            onSuccess: () => {
                showSuccess(t`Email settings saved`);
                emailSettingsQuery.refetch();
            },
            onError: (error) => {
                formErrorHandler(form, error);
            },
        });
    };

    const isLoading = upsertMutation.isPending || emailSettingsQuery.isLoading;
    const smtpEnabled = form.values.smtp_enabled;
    const passwordIsSet = emailSettingsQuery.data?.smtp_password_set;

    return (
        <>
            <HeadingCard
                heading={t`Email Settings`}
                subHeading={t`Configure a custom SMTP server for outgoing emails from this organisation. Leave disabled to use the system default.`}
            />
            <Card className={classes.tabContent}>
                <LoadingMask/>
                <form onSubmit={form.onSubmit(handleSubmit as any)}>
                    <fieldset disabled={isLoading} style={{border: 'none', padding: 0}}>
                        <Checkbox
                            label={t`Use custom SMTP server`}
                            description={t`When enabled, all emails for this organisation will be sent through your own SMTP server.`}
                            {...form.getInputProps('smtp_enabled', {type: 'checkbox'})}
                            mb="md"
                        />

                        {smtpEnabled && (
                            <>
                                <Divider mb="md"/>

                                <TextInput
                                    label={t`SMTP Host`}
                                    placeholder="smtp.example.com"
                                    required
                                    {...form.getInputProps('smtp_host')}
                                />

                                <TextInput
                                    label={t`SMTP Port`}
                                    placeholder="587"
                                    required
                                    type="number"
                                    {...form.getInputProps('smtp_port')}
                                    onChange={(e) =>
                                        form.setFieldValue(
                                            'smtp_port',
                                            e.currentTarget.value ? parseInt(e.currentTarget.value, 10) : undefined
                                        )
                                    }
                                />

                                <Select
                                    label={t`Encryption`}
                                    data={ENCRYPTION_OPTIONS}
                                    placeholder="None"
                                    clearable
                                    {...form.getInputProps('smtp_encryption')}
                                />

                                <TextInput
                                    label={t`SMTP Username`}
                                    placeholder="user@example.com"
                                    {...form.getInputProps('smtp_username')}
                                />

                                <PasswordInput
                                    label={t`SMTP Password`}
                                    placeholder={
                                        passwordIsSet
                                            ? t`Leave blank to keep existing password`
                                            : t`Enter password`
                                    }
                                    description={
                                        passwordIsSet
                                            ? t`A password is currently stored. Enter a new value to replace it.`
                                            : undefined
                                    }
                                    {...form.getInputProps('smtp_password')}
                                />

                                <Divider my="md"/>

                                <Text fw={500} size="sm" mb="xs">{t`From Address`}</Text>

                                <TextInput
                                    label={t`From Email Address`}
                                    placeholder="noreply@example.com"
                                    {...form.getInputProps('mail_from_address')}
                                />

                                <TextInput
                                    label={t`From Name`}
                                    placeholder="My Organisation"
                                    {...form.getInputProps('mail_from_name')}
                                />
                            </>
                        )}

                        <div className={classes.footer}>
                            <Button
                                loading={upsertMutation.isPending}
                                type="submit"
                                fullWidth
                            >
                                {t`Save Settings`}
                            </Button>
                        </div>
                    </fieldset>
                </form>
            </Card>
        </>
    );
};

export default EmailSettings;
