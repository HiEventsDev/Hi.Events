import {GenericModalProps, IdParam, Message, MessageType} from "../../../types.ts";
import {useParams} from "react-router-dom";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {Modal} from "../../common/Modal";
import {Alert, Button, ComboboxItemGroup, LoadingOverlay, MultiSelect, Select, Switch, TextInput} from "@mantine/core";
import {IconAlertCircle, IconSend} from "@tabler/icons-react";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {useForm, UseFormReturnType} from "@mantine/form";
import {useMutation} from "@tanstack/react-query";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {messagesClient} from "../../../api/messages.client.ts";
import {t, Trans} from "@lingui/macro";
import {Editor} from "../../common/Editor";
import {useIsAccountVerified} from "../../../hooks/useIsAccountVerified.ts";

interface EventMessageModalProps extends GenericModalProps {
    orderId?: IdParam,
    ticketId?: IdParam,
    messageType: MessageType,
    attendeeId?: IdParam,
}

const OrderField = ({orderId, eventId}: { orderId: IdParam, eventId: IdParam }) => {
    const {data: order} = useGetOrder(eventId, orderId);

    if (!order) {
        return null;
    }

    return (
        <TextInput
            mt={20}
            label={t`Recipient`}
            disabled
            placeholder={`${order.first_name} ${order.last_name} <${order.email}>`}
        />
    )
}

const AttendeeField = ({orderId, eventId, attendeeId, form}: {
    orderId: IdParam,
    eventId: IdParam,
    attendeeId: IdParam,
    form: UseFormReturnType<any>
}) => {
    const {data: order} = useGetOrder(eventId, orderId);
    const {data: {tickets} = {}} = useGetEvent(eventId);

    if (!order || !tickets || !attendeeId) {
        return null;
    }

    const groups: ComboboxItemGroup[] = tickets.map(ticket => {
        return {
            group: ticket.title,
            items: order.attendees?.filter(a => a.ticket_id === ticket.id).map(attendee => {
                return {
                    value: String(attendee.id),
                    label: attendee.first_name + ' ' + attendee.last_name,
                };
            }) || []
        }
    });

    return (
        <MultiSelect
            mt={20}
            label={t`Message individual attendees`}
            searchable
            data={groups}
            {...form.getInputProps('attendee_ids')}
        />
    )
}

export const SendMessageModal = (props: EventMessageModalProps) => {
    const {onClose, orderId, ticketId, messageType, attendeeId} = props;
    const {eventId} = useParams();
    const {data: event, data: {tickets} = {}} = useGetEvent(eventId);
    const {data: me} = useGetMe();
    const errorHandler = useFormErrorResponseHandler();
    const isPreselectedRecipient = !!(orderId || attendeeId || ticketId);
    const isAccountVerified = useIsAccountVerified();

    const form = useForm({
        initialValues: {
            subject: '',
            message: '',
            message_type: messageType,
            attendee_ids: attendeeId ? [String(attendeeId)] : [],
            ticket_ids: ticketId ? [String(ticketId)] : [],
            order_id: orderId,
            is_test: false,
            send_copy_to_current_user: false,
            type: 'EVENT',
        },
    });

    const mutation = useMutation(
        (message: Partial<Message>) => messagesClient.send(eventId, message as Message),
        {
            onSuccess: () => {
                showSuccess(t`Message Sent`);
                form.reset();
                onClose();
            },
            onError: (error: any) => errorHandler(form, error)
        }
    );

    if (!event || !me || !tickets) {
        return <LoadingOverlay visible/>;
    }

    return (
        <Modal
            withCloseButton
            opened
            onClose={onClose}
            heading={t`Send a message`}
        >
            <form onSubmit={form.onSubmit((values) => mutation.mutate(values))}>
                <fieldset disabled={!isAccountVerified}>
                    {!isPreselectedRecipient && (
                        <Select
                            mt={20}
                            data={[
                                {
                                    value: 'TICKET',
                                    label: t`Attendees with a specific ticket`,
                                },
                                {
                                    value: 'EVENT',
                                    label: t`All attendees of this event`,
                                },
                            ]}
                            label={t`Who is this message to?`}
                            placeholder={t`Please select`}
                            {...form.getInputProps('message_type')}
                        />
                    )}

                    {((form.values.message_type === MessageType.Attendee) && attendeeId && orderId) && (
                        <AttendeeField eventId={eventId} orderId={orderId} attendeeId={attendeeId} form={form}/>
                    )}

                    {((form.values.message_type === MessageType.Ticket && event.tickets)) && (
                        <MultiSelect
                            mt={20}
                            label={t`Message attendees with specific tickets`}
                            searchable
                            data={event.tickets?.map(ticket => {
                                return {
                                    value: String(ticket.id),
                                    label: ticket.title,
                                };
                            })}
                            {...form.getInputProps('ticket_ids')}
                        />
                    )}

                    {(form.values.message_type === MessageType.Order && orderId) && (
                        <OrderField orderId={orderId} eventId={eventId}/>
                    )}

                    <TextInput
                        required
                        mt={20}
                        label={t`Subject`}
                        {...form.getInputProps('subject')}
                    />

                    <Editor
                        label={t`Message Content`}
                        value={form.values.message || ''}
                        onChange={(value) => form.setFieldValue('message', value)}
                        error={form.errors.message}
                    />

                    <Switch
                        mt={20}
                        label={(
                            <Trans>
                                Send a copy to <b>{me?.email}</b>
                            </Trans>
                        )}
                        {...form.getInputProps('send_copy_to_current_user')}
                    />

                    <Switch
                        mt={20}
                        label={(
                            <Trans>
                                Send as a test. This will send the message to your email address instead of the
                                recipients.
                            </Trans>
                        )}
                        {...form.getInputProps('is_test')}
                    />

                    <Alert variant={'outline'} mt={20} icon={<IconAlertCircle size="1rem"/>}
                           title={t`Before you send!`}>
                        {t`Please ensure you only send emails directly related to the order. Promotional emails
                    should not be sent using this form.`}
                    </Alert>

                    {!isAccountVerified && (
                        <Alert mt={20} variant={'light'} icon={<IconAlertCircle size="1rem"/>}>
                            {t`You need to verify your account before you can send messages.`}
                        </Alert>
                    )}

                    <Button mt={20} loading={mutation.isLoading} type={'submit'} fullWidth leftSection={<IconSend/>}>
                        {form.values.is_test ? t`Send Test` : t`Send`}
                    </Button>
                </fieldset>
            </form>
        </Modal>
    )
};