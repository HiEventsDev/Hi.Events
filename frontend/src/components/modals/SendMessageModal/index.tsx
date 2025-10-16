import {GenericModalProps, IdParam, MessageType, ProductType} from "../../../types.ts";
import {useParams} from "react-router";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {Modal} from "../../common/Modal";
import {Alert, Button, ComboboxItemGroup, LoadingOverlay, MultiSelect, Select, Switch, TextInput} from "@mantine/core";
import {IconAlertCircle, IconSend} from "@tabler/icons-react";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {useForm, UseFormReturnType} from "@mantine/form";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {t, Trans} from "@lingui/macro";
import {Editor} from "../../common/Editor";
import {useSendEventMessage} from "../../../mutations/useSendEventMessage.ts";
import {ProductSelector} from "../../common/ProductSelector";
import {useEffect} from "react";
import {useGetAccount} from "../../../queries/useGetAccount.ts";
import {StripeConnectButton} from "../../common/StripeConnectButton";

interface EventMessageModalProps extends GenericModalProps {
    orderId?: IdParam,
    productId?: IdParam,
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
    const {data: {products} = {}} = useGetEvent(eventId);

    if (!order || !products || !attendeeId) {
        return null;
    }

    const groups: ComboboxItemGroup[] = products.map(product => {
        return {
            group: product.title,
            items: order.attendees?.filter(a => a.product_id === product.id).map(attendee => {
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
    const {onClose, orderId, productId, messageType, attendeeId} = props;
    const {eventId} = useParams();
    const {data: event, data: {product_categories} = {}} = useGetEvent(eventId);
    const {data: me} = useGetMe();
    const errorHandler = useFormErrorResponseHandler();
    const isPreselectedRecipient = !!(orderId || attendeeId || productId);
    const {data: account, isFetched: isAccountFetched} = useGetAccount();
    const isAccountVerified = isAccountFetched && account?.is_account_email_confirmed;
    const accountRequiresManualVerification = isAccountFetched && account?.requires_manual_verification;
    const formIsDisabled = !isAccountVerified || accountRequiresManualVerification;

    const sendMessageMutation = useSendEventMessage();

    const form = useForm({
        initialValues: {
            subject: '',
            message: '',
            message_type: messageType,
            attendee_ids: attendeeId ? [String(attendeeId)] : [],
            product_ids: productId ? [String(productId)] : [],
            order_id: orderId,
            is_test: false,
            send_copy_to_current_user: false,
            type: 'EVENT',
            acknowledgement: false,
            order_statuses: ['COMPLETED'],
        },
        validate: {
            acknowledgement: (value) => value === true ? null : t`You must acknowledge that this email is not promotional`,
        }
    });

    const handleSend = (values: any) => {
        sendMessageMutation.mutate({
            eventId: eventId,
            messageData: values,
        }, {
            onSuccess: () => {
                showSuccess(t`Message Sent`);
                form.reset();
                onClose();
            },
            onError: (error: any) => errorHandler(form, error)
        });
    }

    if (!event || !me || !product_categories) {
        return <LoadingOverlay visible/>;
    }

    useEffect(() => {
        form.setFieldValue('product_ids', []);
    }, [form.values.message_type]);

    return (
        <Modal
            withCloseButton
            opened
            onClose={onClose}
            heading={t`Send a message`}
        >
            {!isAccountFetched && (
                <div style={{height: 200}}>
                    <LoadingOverlay visible/>
                </div>
            )}

            <form onSubmit={form.onSubmit(handleSend)}>

                {(!isAccountVerified && isAccountFetched) && (
                    <Alert mt={20} variant={'light'} icon={<IconAlertCircle size="1rem"/>}>
                        {t`You need to verify your account email before you can send messages.`}
                    </Alert>
                )}

                {accountRequiresManualVerification && (
                    <>
                        <Alert mt={20} variant={'light'} icon={<IconAlertCircle size="1rem"/>}
                               title={t`Connect Stripe to enable messaging`}>
                            {t`Due to the high risk of spam, you must connect a Stripe account before you can send messages to attendees.
                             This is to ensure that all event organizers are verified and accountable.`}
                            <br/><br/>
                            <StripeConnectButton/>
                        </Alert>

                    </>
                )}

                {!formIsDisabled && (
                    <fieldset disabled={formIsDisabled}
                              style={{opacity: !formIsDisabled ? 1 : 0.5}}>
                        {!isPreselectedRecipient && (
                            <Select
                                mt={20}
                                data={[
                                    {
                                        value: 'TICKET_HOLDERS',
                                        label: t`Attendees with a specific ticket`,
                                    },
                                    {
                                        value: 'ALL_ATTENDEES',
                                        label: t`All attendees of this event`,
                                    },
                                    {
                                        value: 'ORDER_OWNERS_WITH_PRODUCT',
                                        label: t`Order owners with a specific product`,
                                    },
                                ]}
                                label={t`Who is this message to?`}
                                placeholder={t`Please select`}
                                {...form.getInputProps('message_type')}
                            />
                        )}

                        {((form.values.message_type === MessageType.IndividualAttendees) && attendeeId && orderId) && (
                            <AttendeeField eventId={eventId} orderId={orderId} attendeeId={attendeeId} form={form}/>
                        )}

                        {((form.values.message_type === MessageType.TicketHolders && event.product_categories)) && (
                            <ProductSelector
                                label={t`Message attendees with specific tickets`}
                                placeholder={t`Select tickets`}
                                productCategories={event.product_categories}
                                form={form}
                                productFieldName={'product_ids'}
                                includedProductTypes={[ProductType.Ticket]}
                            />
                        )}

                        {((form.values.message_type === MessageType.OrderOwnersWithProduct && event.product_categories)) && (
                            <>
                                <ProductSelector
                                    label={t`Message order owners with specific products`}
                                    placeholder={t`Select products`}
                                    productCategories={event.product_categories}
                                    form={form}
                                    productFieldName={'product_ids'}
                                    includedProductTypes={[ProductType.Ticket, ProductType.General]}
                                />
                                <MultiSelect
                                    description={t`Only send to orders with these statuses`}
                                    mt={20}
                                    label={t`Order statuses`}
                                    data={[
                                        {value: 'COMPLETED', label: t`Completed`},
                                        {value: 'AWAITING_OFFLINE_PAYMENT', label: t`Awaiting offline payment`},
                                    ]}
                                    {...form.getInputProps('order_statuses')}
                                />
                            </>
                        )}

                        {(form.values.message_type === MessageType.OrderOwner && orderId) && (
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
                            error={form.errors.message as string}
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
                            {t`Only important emails, which are directly related to this event, should be sent using this form.
                         Any misuse, including sending promotional emails, will lead to an immediate account ban.`}
                        </Alert>

                        <Switch mt={20} {...form.getInputProps('acknowledgement', {type: 'checkbox'})}
                                label={(
                                    <Trans>
                                        This email is not promotional and is directly related to the event.
                                    </Trans>
                                )}/>

                        <Button mt={20}
                                loading={sendMessageMutation.isPending}
                                type={'submit'} fullWidth
                                leftSection={<IconSend/>}
                                disabled={!form.values.acknowledgement || !isAccountVerified || accountRequiresManualVerification}>
                            {form.values.is_test ? t`Send Test` : t`Send`}
                        </Button>
                    </fieldset>
                )}
            </form>
        </Modal>
    )
};
