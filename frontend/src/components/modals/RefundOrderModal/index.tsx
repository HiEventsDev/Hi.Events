import {Alert, Button, Checkbox, LoadingOverlay, NumberInput} from "@mantine/core";
import {GenericModalProps, IdParam, Order} from "../../../types.ts";
import {useForm, UseFormReturnType} from "@mantine/form";
import {useParams} from "react-router-dom";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {useEffect} from "react";
import {Currency} from "../../common/Currency";
import {useRefundOrder} from "../../../mutations/useRefundOrder.ts";
import {RefundOrderPayload} from "../../../api/order.client.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {IconInfoCircle} from "@tabler/icons-react";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {Modal} from "../../common/Modal";
import classes from './RefundOrderModal.module.scss';
import {t} from "@lingui/macro";

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
                showSuccess(t`Your refund is processing.`)
                form.reset();
                onClose();
            },
            onError: (error) => {
                formErrorResponseHandler(form, error);
            }
        }
    );

    const modalForm = ({order, form}: { order: Order, form: UseFormReturnType<RefundOrderPayload> }) => {
        return (
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <div className={classes.refundSummary}>
                    <div className={classes.block}>
                        <div className={classes.title}>
                            {t`Total order amount`}
                        </div>
                        <div className={classes.amount}>
                            <Currency currency={order.currency} price={order.total_gross}/>
                        </div>
                    </div>
                    <div className={classes.block}>
                        <div className={classes.title}>
                            {t`Total refunded`}
                        </div>
                        <div className={classes.amount}>
                            <Currency currency={order.currency} price={order.total_refunded}/>
                        </div>
                    </div>
                    <div className={classes.block}>
                        <div className={classes.title}>
                            {t`Total remaining`}
                        </div>
                        <div className={classes.amount}>
                            <Currency currency={order.currency} price={order.total_gross - order.total_refunded}/>
                        </div>
                    </div>
                </div>

                <NumberInput
                    max={order.total_gross - order.total_refunded}
                    min={0}
                    decimalScale={2}
                    fixedDecimalScale
                    {...form.getInputProps('amount')}
                    label={t`Refund amount (${order?.currency})`}
                    placeholder={t`10.00`}
                />
                <Checkbox mt={20} {...form.getInputProps('notify_buyer', {type: 'checkbox'})}
                          label={t`Notify buyer of refund`}/>

                <Checkbox mt={20} {...form.getInputProps('cancel_order', {type: 'checkbox'})}
                          label={t`Cancel order`}/>

                <Button loading={mutation.isLoading} fullWidth mt={20} type={'submit'}>{t`Issue refund`}</Button>
            </form>);
    };

    const CannotRefund = ({message}: { message: string }) => {
        return (
            <>
                <Alert icon={<IconInfoCircle/>} color={'blue'}>
                    {message}
                </Alert>
                <Button mt={20} fullWidth onClick={onClose}>{t`Close`}</Button>
            </>
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
            heading={t`Refund Order`}
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
