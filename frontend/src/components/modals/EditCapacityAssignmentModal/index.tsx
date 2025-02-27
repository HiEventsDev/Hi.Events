import {CapacityAssignmentRequest, GenericModalProps, IdParam, ProductCategory} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {CapacityAssigmentForm} from "../../forms/CapaciyAssigmentForm";
import {useForm} from "@mantine/form";
import {Button} from "@mantine/core";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useEditCapacityAssignment} from "../../../mutations/useEditCapacityAssignment.ts";
import {useGetEventCapacityAssignment} from "../../../queries/useGetCapacityAssignment.ts";
import {useEffect} from "react";

interface EditCapacityAssignmentModalProps {
    capacityAssignmentId: IdParam;
}

export const EditCapacityAssignmentModal = ({
                                                onClose,
                                                capacityAssignmentId
                                            }: GenericModalProps & EditCapacityAssignmentModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: capacityAssignment} = useGetEventCapacityAssignment(
        capacityAssignmentId,
        eventId
    );
    const {data: event} = useGetEvent(eventId);
    const form = useForm<CapacityAssignmentRequest>({
        initialValues: {
            name: '',
            capacity: undefined,
            status: 'ACTIVE',
            product_ids: [],
        }
    });
    const editMutation = useEditCapacityAssignment();

    const handleSubmit = (requestData: CapacityAssignmentRequest) => {
        editMutation.mutate({
            eventId: eventId,
            capacityAssignmentData: requestData,
            capacityAssignmentId: capacityAssignmentId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully updated Capacity Assignment`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        })
    }

    useEffect(() => {
        if (capacityAssignment) {
            form.setValues({
                name: capacityAssignment.name,
                capacity: capacityAssignment.capacity,
                status: capacityAssignment.status,
                product_ids: capacityAssignment.products?.map(product => String(product.id)),
            });
        }
    }, [capacityAssignment]);

    return (
        <Modal opened onClose={onClose} heading={t`Edit Capacity Assignment`}>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                {event && <CapacityAssigmentForm form={form} productsCategories={event.product_categories as ProductCategory[]}/>}
                <Button
                    type={'submit'}
                    fullWidth
                    loading={editMutation.isPending}
                >
                    {t`Edit Capacity Assignment`}
                </Button>
            </form>
        </Modal>
    );
}

