import {CapacityAssignmentRequest, GenericModalProps, Ticket} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {CapaciyAssigmentForm} from "../../forms/CapaciyAssigmentForm";
import {useForm} from "@mantine/form";
import {Button} from "@mantine/core";
import {useCreateCapacityAssignment} from "../../../mutations/useCreateCapacityAssignment.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useParams} from "react-router-dom";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetEvent} from "../../../queries/useGetEvent.ts";

export const CreateCapacityAssignmentModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: event} = useGetEvent(eventId);
    const form = useForm<CapacityAssignmentRequest>({
        initialValues: {
            name: '',
            capacity: undefined,
            applies_to: 'EVENT',
            status: 'ACTIVE',
            ticket_ids: [],
        }
    });
    const createMutation = useCreateCapacityAssignment();

    const handleSubmit = (requestData: CapacityAssignmentRequest) => {
        createMutation.mutate({
            eventId: eventId,
            capacityAssignmentData: requestData,
        }, {
            onSuccess: () => {
                showSuccess(t`Capacity Assignment created successfully`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        })
    }

    return (
        <Modal opened onClose={onClose} heading={t`Create Capacity Assignment`}>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                {event && <CapaciyAssigmentForm form={form} tickets={event.tickets as Ticket[]}/>}
                <Button
                    type={'submit'}
                    fullWidth
                    loading={createMutation.isLoading}
                >
                    {t`Create Capacity Assignment`}
                </Button>
            </form>
        </Modal>
    );
}
