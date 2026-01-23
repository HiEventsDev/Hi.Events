import {Button, NumberInput, Stack} from "@mantine/core";
import {GenericModalProps, IdParam} from "../../../types";
import {useForm} from "@mantine/form";
import {Modal} from "../../common/Modal";
import {showSuccess, showError} from "../../../utilites/notifications";
import {t} from "@lingui/macro";
import {useUpdateAccountConfiguration} from "../../../mutations/useUpdateAccountConfiguration";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler";
import {AccountConfiguration} from "../../../api/admin.client";
import {useEffect} from "react";

interface EditAccountConfigurationModalProps extends GenericModalProps {
    accountId: IdParam;
    configuration?: AccountConfiguration;
    currencyCode?: string;
}

interface FormValues {
    fixed_fee: number;
    percentage_fee: number;
}

export const EditAccountConfigurationModal = ({
    onClose,
    accountId,
    configuration,
    currencyCode = 'USD',
}: EditAccountConfigurationModalProps) => {
    const updateMutation = useUpdateAccountConfiguration(accountId);
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm<FormValues>({
        initialValues: {
            fixed_fee: 0,
            percentage_fee: 0,
        },
        validate: {
            fixed_fee: (value) => {
                if (value < 0) {
                    return t`Fixed fee must be 0 or greater`;
                }
                return null;
            },
            percentage_fee: (value) => {
                if (value < 0 || value > 100) {
                    return t`Percentage fee must be between 0 and 100`;
                }
                return null;
            },
        },
    });

    useEffect(() => {
        if (configuration?.application_fees) {
            form.setValues({
                fixed_fee: configuration.application_fees.fixed / 100,
                percentage_fee: configuration.application_fees.percentage,
            });
        }
    }, [configuration]);

    const handleSubmit = (values: FormValues) => {
        updateMutation.mutate(
            {
                fixed_fee: Math.round(values.fixed_fee * 100),
                percentage_fee: values.percentage_fee,
            },
            {
                onSuccess: () => {
                    showSuccess(t`Account configuration updated successfully`);
                    onClose();
                },
                onError: (error: any) => {
                    formErrorHandler(form, error);
                    showError(
                        error?.response?.data?.message ||
                        t`Failed to update account configuration`
                    );
                }
            }
        );
    };

    return (
        <Modal heading={t`Edit Application Fees`} onClose={onClose} opened>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <Stack gap="md">
                    <NumberInput
                        label={t`Fixed Fee`}
                        description={t`Fixed fee amount in ${currencyCode}`}
                        placeholder="0.00"
                        decimalScale={2}
                        fixedDecimalScale
                        min={0}
                        {...form.getInputProps('fixed_fee')}
                    />

                    <NumberInput
                        label={t`Percentage Fee`}
                        description={t`Percentage fee (0-100%)`}
                        placeholder="0"
                        decimalScale={2}
                        fixedDecimalScale
                        min={0}
                        max={100}
                        suffix="%"
                        {...form.getInputProps('percentage_fee')}
                    />

                    <Button
                        fullWidth
                        loading={updateMutation.isPending}
                        type="submit"
                    >
                        {t`Save Configuration`}
                    </Button>
                </Stack>
            </form>
        </Modal>
    );
};
