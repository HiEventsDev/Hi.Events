import {useForm} from "@mantine/form";
import {Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {TaxAndFeeForm} from "../../forms/TaxAndFeeForm";
import {Modal, ModalFooter} from "../../common/Modal";
import {GenericModalProps, TaxAndFee, TaxAndFeeType} from "../../../types.ts";
import {useUpdateTaxOrFee} from "../../../mutations/useUpdateTaxOrFee.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../utilites/notifications.tsx";

interface EditTaxOrFeeModalProps extends GenericModalProps {
    taxOrFee: TaxAndFee;
}

export const EditTaxOrFeeModal = ({onClose, taxOrFee}: EditTaxOrFeeModalProps) => {
    const updateMutation = useUpdateTaxOrFee();
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm<TaxAndFee>({
        initialValues: taxOrFee,
    });

    const isTax = form.values.type === TaxAndFeeType.Tax;

    const handleEdit = (values: TaxAndFee) => {
        updateMutation.mutate({taxOrFeeId: taxOrFee.id, taxOrFeeData: values}, {
            onSuccess: () => {
                showSuccess(isTax ? t`Tax updated` : t`Fee updated`);
                onClose();
            },
            onError: (error: any) => formErrorHandler(form, error),
        });
    };

    return (
        <Modal opened onClose={onClose} heading={isTax ? t`Edit tax` : t`Edit fee`} size={520}>
            <form onSubmit={(event) => {
                event.stopPropagation();
                form.onSubmit(handleEdit)(event);
            }}>
                <TaxAndFeeForm form={form}/>
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button type="submit" loading={updateMutation.isPending} data-testid="tax-or-fee-submit">
                        {t`Save changes`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
