import {useForm} from "@mantine/form";
import {Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {TaxAndFeeForm} from "../../forms/TaxAndFeeForm";
import {Modal, ModalFooter} from "../../common/Modal";
import {GenericModalProps, TaxAndFee, TaxAndFeeCalculationType, TaxAndFeeType} from "../../../types.ts";
import {useCreateTaxOrFee} from "../../../mutations/useCreateTaxOrFee.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../utilites/notifications.tsx";

interface CreateTaxOrFeeModalProps extends GenericModalProps {
    onCreated?: (taxOrFee: TaxAndFee) => void;
}

export const CreateTaxOrFeeModal = ({onClose, onCreated}: CreateTaxOrFeeModalProps) => {
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

    const isTax = form.values.type === TaxAndFeeType.Tax;

    const handleCreate = (values: TaxAndFee) => {
        createMutation.mutate({taxOrFeeData: values}, {
            onSuccess: (response) => {
                showSuccess(isTax ? t`Tax created` : t`Fee created`);
                onCreated?.(response.data);
                onClose();
            },
            onError: (error) => formErrorHandler(form, error),
        });
    };

    return (
        <Modal opened onClose={onClose} heading={t`Add tax or fee`} size={520}>
            <form onSubmit={(event) => {
                event.stopPropagation();
                form.onSubmit(handleCreate)(event);
            }}>
                <TaxAndFeeForm form={form}/>
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button type="submit" loading={createMutation.isPending} data-testid="tax-or-fee-submit">
                        {isTax ? t`Create tax` : t`Create fee`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
