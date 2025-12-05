import {Button, Select, Stack, Switch, Text, TextInput} from "@mantine/core";
import {GenericModalProps, IdParam} from "../../../types";
import {useForm} from "@mantine/form";
import {Modal} from "../../common/Modal";
import {showSuccess, showError} from "../../../utilites/notifications";
import {t} from "@lingui/macro";
import {useUpdateAdminAccountVatSettings} from "../../../mutations/useUpdateAdminAccountVatSettings";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler";
import {AccountVatSetting} from "../../../api/admin.client";
import {useEffect} from "react";

interface EditAccountVatSettingsModalProps extends GenericModalProps {
    accountId: IdParam;
    vatSetting?: AccountVatSetting;
}

interface FormValues {
    vat_registered: boolean;
    vat_number: string;
    business_name: string;
    business_address: string;
    vat_country_code: string;
}

const EU_COUNTRIES = [
    { value: 'AT', label: 'Austria' },
    { value: 'BE', label: 'Belgium' },
    { value: 'BG', label: 'Bulgaria' },
    { value: 'HR', label: 'Croatia' },
    { value: 'CY', label: 'Cyprus' },
    { value: 'CZ', label: 'Czech Republic' },
    { value: 'DK', label: 'Denmark' },
    { value: 'EE', label: 'Estonia' },
    { value: 'FI', label: 'Finland' },
    { value: 'FR', label: 'France' },
    { value: 'DE', label: 'Germany' },
    { value: 'GR', label: 'Greece' },
    { value: 'HU', label: 'Hungary' },
    { value: 'IE', label: 'Ireland' },
    { value: 'IT', label: 'Italy' },
    { value: 'LV', label: 'Latvia' },
    { value: 'LT', label: 'Lithuania' },
    { value: 'LU', label: 'Luxembourg' },
    { value: 'MT', label: 'Malta' },
    { value: 'NL', label: 'Netherlands' },
    { value: 'PL', label: 'Poland' },
    { value: 'PT', label: 'Portugal' },
    { value: 'RO', label: 'Romania' },
    { value: 'SK', label: 'Slovakia' },
    { value: 'SI', label: 'Slovenia' },
    { value: 'ES', label: 'Spain' },
    { value: 'SE', label: 'Sweden' },
];

export const EditAccountVatSettingsModal = ({
    onClose,
    accountId,
    vatSetting,
}: EditAccountVatSettingsModalProps) => {
    const updateMutation = useUpdateAdminAccountVatSettings(accountId);
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm<FormValues>({
        initialValues: {
            vat_registered: false,
            vat_number: '',
            business_name: '',
            business_address: '',
            vat_country_code: '',
        },
    });

    useEffect(() => {
        if (vatSetting) {
            form.setValues({
                vat_registered: vatSetting.vat_registered ?? false,
                vat_number: vatSetting.vat_number || '',
                business_name: vatSetting.business_name || '',
                business_address: vatSetting.business_address || '',
                vat_country_code: vatSetting.vat_country_code || '',
            });
        }
    }, [vatSetting]);

    const handleSubmit = (values: FormValues) => {
        updateMutation.mutate(
            {
                vat_registered: values.vat_registered,
                vat_number: values.vat_registered ? values.vat_number.trim() : null,
                business_name: values.vat_registered ? values.business_name.trim() : null,
                business_address: values.vat_registered ? values.business_address.trim() : null,
                vat_country_code: values.vat_registered ? values.vat_country_code : null,
            },
            {
                onSuccess: () => {
                    showSuccess(t`VAT settings updated successfully`);
                    onClose();
                },
                onError: (error: any) => {
                    formErrorHandler(form, error);
                    showError(
                        error?.response?.data?.message ||
                        t`Failed to update VAT settings`
                    );
                }
            }
        );
    };

    return (
        <Modal heading={t`Edit VAT Settings`} onClose={onClose} opened>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <Stack gap="md">
                    <Switch
                        label={t`VAT Registered`}
                        description={t`Is this account VAT registered in the EU?`}
                        {...form.getInputProps('vat_registered', { type: 'checkbox' })}
                    />

                    {form.values.vat_registered && (
                        <>
                            <TextInput
                                label={t`VAT Number`}
                                description={t`Enter the VAT number including country code`}
                                placeholder="IE1234567A"
                                required={form.values.vat_registered}
                                {...form.getInputProps('vat_number')}
                            />

                            <Select
                                label={t`VAT Country`}
                                description={t`Select the country where VAT is registered`}
                                placeholder={t`Select country`}
                                data={EU_COUNTRIES}
                                searchable
                                {...form.getInputProps('vat_country_code')}
                            />

                            <TextInput
                                label={t`Business Name`}
                                description={t`Legal business name for VAT purposes`}
                                placeholder={t`Enter business name`}
                                {...form.getInputProps('business_name')}
                            />

                            <TextInput
                                label={t`Business Address`}
                                description={t`Business address for VAT purposes`}
                                placeholder={t`Enter business address`}
                                {...form.getInputProps('business_address')}
                            />

                            {vatSetting?.vat_validated !== undefined && (
                                <Text size="sm" c={vatSetting.vat_validated ? 'green' : 'red'}>
                                    {vatSetting.vat_validated
                                        ? t`Current VAT number is validated`
                                        : t`Current VAT number validation failed`}
                                </Text>
                            )}
                        </>
                    )}

                    <Button
                        fullWidth
                        loading={updateMutation.isPending}
                        type="submit"
                    >
                        {t`Save VAT Settings`}
                    </Button>
                </Stack>
            </form>
        </Modal>
    );
};
