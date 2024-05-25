import { useForm } from "@mantine/form";
import { TaxAndFeeForm } from "../../forms/TaxAndFeeForm";
import { GenericModalProps, TaxAndFee, TaxAndFeeCalculationType, TaxAndFeeType } from "../../../types.ts";
import { Modal } from "../../common/Modal";
import { Button } from "@mantine/core";
import { useCreateTaxOrFee } from "../../../mutations/useCreateTaxOrFee.ts";
import { useFormErrorResponseHandler } from "../../../hooks/useFormErrorResponseHandler.tsx";
import { showSuccess } from "../../../utilites/notifications.tsx";
import {t, Trans} from "@lingui/macro";

export const CreateTaxOrFeeModal = ({ onClose }: GenericModalProps) => {
    const createMutation = useCreateTaxOrFee();
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm<TaxAndFee>({
        initialValues: {
            name: '',
            type: TaxAndFeeType.Tax,
            calculation_type: TaxAndFeeCalculationType.Percentage,
            rate: undefined,
            description: '',
            is_default: true,
            is_active: true,
        },
    });

    const handleCreate = (values: TaxAndFee) => {
        createMutation.mutate({
            taxOrFeeData: values,
        }, {
            onSuccess: () => {
                showSuccess(<Trans>{form.values.type === TaxAndFeeType.Tax ? t`Tax` : t`Fee`} created successfully</Trans>);
                form.reset();
                onClose();
            },
            onError: (error) => formErrorHandler(form, error)
        });
    };

    return (
        <Modal heading={t`Create Tax or Fee`} onClose={onClose} opened>
            <form onSubmit={form.onSubmit(values => handleCreate(values))}>
                <TaxAndFeeForm form={form} />
                <Button
                    fullWidth
                    loading={createMutation.isLoading}
                    type={'submit'}>
                    <Trans>Create {form.values.type === TaxAndFeeType.Tax ? t`Tax` : t`Fee`}</Trans>
                </Button>
            </form>
        </Modal>
    )
}
