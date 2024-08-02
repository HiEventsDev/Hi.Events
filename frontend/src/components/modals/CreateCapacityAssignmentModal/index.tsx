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
import {NoResultsSplash} from "../../common/NoResultsSplash";
import {IconPlus} from "@tabler/icons-react";

export const CreateCapacityAssignmentModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: event} = useGetEvent(eventId);
    const form = useForm<CapacityAssignmentRequest>({
        initialValues: {
            name: '',
            capacity: undefined,
            status: 'ACTIVE',
            ticket_ids: [],
        }
    });
    const createMutation = useCreateCapacityAssignment();
    const eventHasTickets = event?.tickets && event.tickets.length > 0;

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

    const NoTickets = () => {
        return (
            <NoResultsSplash
                imageHref={'/blank-slate/tickets.svg'}
                heading={t`Please create a ticket`}
                subHeading={(
                    <>
                        <p>
                            {t`You'll need at a ticket before you can create a capacity assignment.`}
                        </p>
                        <Button
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => window.location.href = `/manage/event/${eventId}/tickets/#create-ticket`}
                        >
                            {t`Create a Ticket`}
                        </Button>
                    </>
                )}
            />
        );
    }

    return (
        <Modal opened onClose={onClose} heading={eventHasTickets ? t`Create Capacity Assignment` : null}>
            {!eventHasTickets && <NoTickets/>}
            {eventHasTickets && (
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
            )}
        </Modal>
    );
}
