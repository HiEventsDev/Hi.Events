import {CheckInListRequest, GenericModalProps, Ticket} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {CheckInListForm} from "../../forms/CheckInListForm";
import {useForm} from "@mantine/form";
import {Button} from "@mantine/core";
import {useCreateCheckInList} from "../../../mutations/useCreateCheckInList.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useParams} from "react-router-dom";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {NoResultsSplash} from "../../common/NoResultsSplash";
import {IconPlus} from "@tabler/icons-react";

export const CreateCheckInListModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: event} = useGetEvent(eventId);
    const form = useForm<CheckInListRequest>({
        initialValues: {
            name: '',
            description: '',
            expires_at: '',
            activates_at: '',
            ticket_ids: [],
        }
    });
    const createMutation = useCreateCheckInList();
    const eventHasTickets = event?.tickets && event.tickets.length > 0;

    const handleSubmit = (requestData: CheckInListRequest) => {
        createMutation.mutate({
            eventId: eventId,
            checkInListData: requestData,
        }, {
            onSuccess: () => {
                showSuccess(t`Check-In List created successfully`);
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
                            {t`You'll need a ticket before you can create a check-in list.`}
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
        <Modal opened onClose={onClose} heading={eventHasTickets ? t`Create Check-In List` : null}>
            {!eventHasTickets && <NoTickets/>}
            {eventHasTickets && (
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    {event && <CheckInListForm form={form} tickets={event.tickets as Ticket[]}/>}
                    <Button
                        type={'submit'}
                        fullWidth
                        loading={createMutation.isLoading}
                    >
                        {t`Create Check-In List`}
                    </Button>
                </form>
            )}
        </Modal>
    );
}
