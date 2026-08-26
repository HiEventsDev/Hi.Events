import {Button, Checkbox, Group, LoadingOverlay, NumberInput, Paper, Stack, Text, Title} from "@mantine/core";
import {GenericModalProps, IdParam, Order} from "../../../types.ts";
import {useForm, UseFormReturnType} from "@mantine/form";
import {useParams} from "react-router";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {useEffect} from "react";
import {Currency} from "../../common/Currency";
import {useRefundOrder} from "../../../mutations/useRefundOrder.ts";
import {RefundOrderPayload} from "../../../api/order.client.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {IconCash, IconCreditCard} from "@tabler/icons-react";
import {Callout} from "../../common/Callout";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {Modal} from "../../common/Modal";
import classes from './RefundOrderModal.module.scss';
import {t} from "@lingui/macro";
import {isOfflineOrder} from "../../../utilites/orderHelper.ts";

interface RefundOrderModalProps extends GenericModalProps {
    orderId: IdParam;
}

export const RefundOrderModal = ({onClose, orderId}: RefundOrderModalProps) => {
    const {eventId} = useParams();
    const {data: order} = useGetOrder(eventId, orderId);
    const mutation = useRefundOrder();
    const formErrorResponseHandler = useFormErrorResponseHandler();
    const form = useForm({
        initialValues: {
            amount: 0,
            notify_buyer: false,
            cancel_order: false,
        },
    });
    const isRefundPending = order?.refund_status === 'REFUND_PENDING';
    const isRefunded = order?.refund_status === 'REFUNDED';

    useEffect(() => {
        if (!order) {
            return;
        }
        form.setFieldValue('amount', order.total_gross - order.total_refunded)
    }, [order]);

    if (!order) {
        return <LoadingOverlay visible/>;
    }

    const handleSubmit = (values: RefundOrderPayload) => mutation.mutate({
            eventId: eventId,
            orderId: orderId,
            refundData: {
                amount: values.amount,
                notify_buyer: values.notify_buyer,
                cancel_order: values.cancel_order,
            },
        },
        {
            onSuccess: () => {
                showSuccess(isOfflineOrder(order) ? t`Refund recorded successfully.` : t`Your refund is processing.`)
                form.reset();
                onClose();
            },
            onError: (error) => {
                formErrorResponseHandler(form, error);
            }
        }
    );

    const modalForm = ({order, form}: { order: Order, form: UseFormReturnType<RefundOrderPayload> }) => {
        const remainingAmount = order.total_gross - order.total_refunded;
        const isPartialRefund = form.values.amount < remainingAmount;
        const isOffline = isOfflineOrder(order);

        return (
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <Stack gap="md">
                    {isOffline && (
                        <Callout variant="warning">
                            {t`This order was paid offline. Refunding it will only update your reporting — you will need to return the payment to the customer yourself.`}
                        </Callout>
                    )}
                    <Paper radius="md" p="md" withBorder>
                        <Stack gap="sm">
                            <Group justify="space-between">
                                <Text size="sm" c="dimmed">{t`Order Total`}</Text>
                                <Text size="lg" fw={600}>
                                    <Currency currency={order.currency} price={order.total_gross}/>
                                </Text>
                            </Group>
                            {order.total_refunded > 0 && (
                                <Group justify="space-between">
                                    <Text size="sm" c="dimmed">{t`Already Refunded`}</Text>
                                    <Text size="lg" c="red">
                                        -<Currency currency={order.currency} price={order.total_refunded}/>
                                    </Text>
                                </Group>
                            )}
                            <Group justify="space-between" className={classes.remainingRow}>
                                <Text size="sm" fw={500}>{t`Available to Refund`}</Text>
                                <Text size="lg" fw={700}>
                                    <Currency currency={order.currency} price={remainingAmount}/>
                                </Text>
                            </Group>
                        </Stack>
                    </Paper>

                    <NumberInput
                        size="md"
                        max={remainingAmount}
                        min={0.01}
                        decimalScale={2}
                        fixedDecimalScale
                        {...form.getInputProps('amount')}
                        label={t`Refund amount`}
                        placeholder={remainingAmount.toFixed(2)}
                        description={isPartialRefund ? t`Partial refund` : t`Full refund`}
                        leftSection={<IconCash size={20}/>}
                        rightSectionWidth={50}
                        rightSection={<Text size="sm" c="dimmed">{order.currency}</Text>}
                        required
                        styles={{
                            input: {
                                fontWeight: 600,
                                fontSize: '1.1rem'
                            }
                        }}
                    />
                    <Stack gap="xs">
                        <Checkbox
                            {...form.getInputProps('notify_buyer', {type: 'checkbox'})}
                            label={t`Send refund notification email`}
                            description={t`Customer will receive an email confirming the refund`}
                        />

                        {order.status !== 'CANCELLED' && (
                            <Checkbox
                                {...form.getInputProps('cancel_order', {type: 'checkbox'})}
                                label={t`Also cancel this order`}
                                description={t`Cancel all products and release them back to the pool`}
                            />
                        )}
                    </Stack>

                    {(isPartialRefund && Number(form.values.amount) > 0) && (
                        <Callout variant="info">
                            {t`You are issuing a partial refund. The customer will be refunded ${Number(form.values.amount).toFixed(2)} ${order.currency}.`}
                        </Callout>
                    )}

                    <Button
                        loading={mutation.isPending}
                        fullWidth
                        size="md"
                        type={'submit'}
                        leftSection={isOffline ? <IconCash size={20}/> : <IconCreditCard size={20}/>}
                    >
                        {isOffline ? t`Record Refund` : t`Process Refund`}
                    </Button>
                </Stack>
            </form>);
    };

    const CannotRefund = ({message}: { message: string }) => {
        return (
            <Stack gap="md">
                <Callout variant="info">
                    {message}
                </Callout>
                <Button fullWidth onClick={onClose} variant="light">{t`Close`}</Button>
            </Stack>
        )
    }

    const getModalBody = () => {
        if (order.is_manually_created) {
            return <CannotRefund message={t`You cannot refund a manually created order.`}/>
        }

        if (isRefundPending) {
            return <CannotRefund
                message={t`There is a refund pending. Please wait for it to complete before requesting another refund.`}/>
        }

        if (isRefunded) {
            return <CannotRefund message={t`This order has already been refunded.`}/>
        }

        return modalForm({order, form});
    }

    return (
        <Modal
            opened
            onClose={onClose}
            heading={
                <Group gap="xs">
                    <IconCreditCard size={24}/>
                    <Title order={4}>{t`Refund Order ${order?.public_id || ''}`}</Title>
                </Group>
            }
            size="md"
            padding={'lg'}
            overlayProps={{
                opacity: 0.55,
                blur: 3,
            }}
        >
            {getModalBody()}
        </Modal>
    )
};
