import {GenericModalProps, IdParam,} from "../../../types.ts";
import {useParams} from "react-router";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {Modal} from "../../common/Modal";
import {Alert, Button, Checkbox, LoadingOverlay} from "@mantine/core";
import {IconInfoCircle} from "@tabler/icons-react";
import classes from './CancelOrderModal.module.scss';
import {OrderDetails} from "../../common/OrderDetails";
import {AttendeeList} from "../../common/AttendeeList";
import {t} from "@lingui/macro";
import {useCancelOrder} from "../../../mutations/useCancelOrder.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {useState} from "react";

interface RefundOrderModalProps extends GenericModalProps {
    orderId: IdParam,
}

export const CancelOrderModal = ({onClose, orderId}: RefundOrderModalProps) => {
    const {eventId} = useParams();
    const {data: order} = useGetOrder(eventId, orderId);
    const {data: event, data: {products} = {}} = useGetEvent(eventId);
    const cancelOrderMutation = useCancelOrder();
    const [shouldRefund, setShouldRefund] = useState(true);

    const isRefundable = order && !order.is_free_order
        && order.status !== 'AWAITING_OFFLINE_PAYMENT'
        && order.payment_provider === 'STRIPE'
        && order.refund_status !== 'REFUNDED';

    const handleCancelOrder = () => {
        cancelOrderMutation.mutate({
            eventId, 
            orderId,
            refund: shouldRefund && isRefundable
        }, {
            onSuccess: () => {
                const message = shouldRefund && isRefundable 
                    ? t`Order has been canceled and refunded. The order owner has been notified.`
                    : t`Order has been canceled and the order owner has been notified.`;
                showSuccess(message);
                onClose();
            },
            onError: (error: any) => {
                showError(error?.response?.data?.message || t`Failed to cancel order`);
            }
        });
    }

    if (!order || !event) {
        return <LoadingOverlay visible/>;
    }

    return (
        <Modal
            heading={t`Cancel Order ${order.public_id}`}
            opened
            onClose={onClose}
        >
            <OrderDetails order={order} event={event}/>

            {products && <AttendeeList order={order} products={products}/>}

            <Alert className={classes.alert} variant="light" color="blue" title={t`Please Note`}
                   icon={<IconInfoCircle/>}>
                {t`Canceling will cancel all attendees associated with this order, and release the tickets back into the available pool.`}
            </Alert>

            {isRefundable && (
                <Checkbox
                    mt={20}
                    mb={20}
                    checked={shouldRefund}
                    onChange={(event) => setShouldRefund(event.currentTarget.checked)}
                    label={t`Also refund this order`}
                    description={t`The full order amount will be refunded to the customer's original payment method.`}
                />
            )}

            <Button loading={cancelOrderMutation.isPending} className={'mb20'} color={'red'} fullWidth
                    onClick={handleCancelOrder}>
                {t`Cancel Order`}
            </Button>
        </Modal>
    )
};
