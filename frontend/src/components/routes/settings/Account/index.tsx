import {useEffect} from "react";
import {Select, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {t} from "@lingui/macro";
import {currencies} from "../../../../../data/currencies.ts";
import {timezones} from "../../../../../data/timezones.ts";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";
import {useUpdateAccount} from "../../../../mutations/useUpdateAccount.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {
    SettingsFooter,
    SettingsPage,
    SettingsRow,
    SettingsSaveFooter,
    SettingsSection,
} from "../../../common/SettingsPage";
import {DeleteAccountSection} from "./DeleteAccountSection.tsx";
import {SingleSignOnSection} from "../../../../ee/sso/components/SingleSignOn";
import {useLicensedFeature} from "../../../../ee/licensing/hooks/useLicensedFeature.ts";
import {FeatureFlag} from "../../../../constants/featureFlags.ts";

const currencyOptions = Object.entries(currencies).map(([label, value]) => ({value, label}));

const AccountSettings = () => {
    const {data: account} = useGetAccount();
    const updateMutation = useUpdateAccount();
    const formErrorHandler = useFormErrorResponseHandler();
    const canManageAccount = useAccess().can('MANAGE_ACCOUNT');
    const enterpriseSso = useLicensedFeature(FeatureFlag.ENTERPRISE_SSO);

    const form = useForm({
        mode: 'controlled',
        initialValues: {
            name: '',
            currency_code: '',
            timezone: '',
        },
    });

    useEffect(() => {
        if (!account || form.isDirty()) {
            return;
        }
        const values = {
            name: account.name ?? '',
            currency_code: account.currency_code ?? '',
            timezone: account.timezone ?? '',
        };
        form.setInitialValues(values);
        form.setValues(values);
    }, [account]);

    const handleSubmit = (values: typeof form.values) => {
        updateMutation.mutate({accountData: values}, {
            onSuccess: () => {
                form.resetDirty();
                showSuccess(t`Account updated successfully`);
            },
            onError: (error) => formErrorHandler(form, error),
        });
    };

    const isPendingDeletion = !!account?.deletion_request;
    const isReadOnly = !canManageAccount || isPendingDeletion;

    return (
        <SettingsPage
            title={t`General`}
            description={t`Your account name and its default currency and timezone.`}
        >
            <SettingsSection>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <fieldset disabled={!account || isReadOnly || updateMutation.isPending}>
                        <SettingsRow
                            label={t`Account name`}
                            htmlFor="account-name"
                        >
                            <TextInput id="account-name" {...form.getInputProps('name')}/>
                        </SettingsRow>
                        <SettingsRow
                            label={t`Currency`}
                            description={t`Prefilled when you create an organizer.`}
                            htmlFor="account-currency"
                        >
                            <Select
                                id="account-currency"
                                searchable
                                data={currencyOptions}
                                allowDeselect={false}
                                {...form.getInputProps('currency_code')}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`Timezone`}
                            description={t`Used for dates in account emails, like deletion notices.`}
                            htmlFor="account-timezone"
                        >
                            <Select
                                id="account-timezone"
                                searchable
                                data={timezones}
                                allowDeselect={false}
                                {...form.getInputProps('timezone')}
                            />
                        </SettingsRow>
                    </fieldset>
                    {isReadOnly ? (
                        <SettingsFooter
                            hint={isPendingDeletion
                                ? t`Cancel the account deletion to make changes.`
                                : t`Only account admins can change these settings.`}
                        />
                    ) : (
                        <SettingsSaveFooter
                            isDirty={form.isDirty()}
                            isSaving={updateMutation.isPending}
                            onDiscard={form.reset}
                            saveTestId="account-settings-save-button"
                        />
                    )}
                </form>
            </SettingsSection>

            {canManageAccount && enterpriseSso.isVisible && account?.id && !isPendingDeletion && (
                <SingleSignOnSection accountId={account.id} setupLocked={enterpriseSso.isSetupLocked}/>
            )}

            {canManageAccount && <DeleteAccountSection/>}
        </SettingsPage>
    );
};

export default AccountSettings;
