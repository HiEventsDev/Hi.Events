import {GenericModalProps, IdParam,} from "../../../types.ts";
import {useParams} from "react-router-dom";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {Modal} from "../../common/Modal";
import {Alert, Button, LoadingOverlay} from "@mantine/core";
import {IconInfoCircle} from "@tabler/icons-react";
import classes from './CancelOrderModal.module.scss';
import {OrderDetails} from "../../common/OrderDetails";
import {AttendeeList} from "../../common/AttendeeList";
import {t} from "@lingui/macro";
import {useCancelOrder} from "../../../mutations/useCancelOrder.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";

interface RefundOrderModalProps extends GenericModalProps {
    orderId: IdParam,
}

export const CancelOrderModal = ({onClose, orderId}: RefundOrderModalProps) => {
    const {eventId} = useParams();
    // const queryClient = useQueryClient();
    const {data: order} = useGetOrder(eventId, orderId);
    const {data: event, data: {tickets} = {}} = useGetEvent(eventId);
    const cancelOrderMutation = useCancelOrder();

    const handleCancelOrder = () => {
        cancelOrderMutation.mutate({eventId, orderId}, {
            onSuccess: () => {
                showSuccess(t`Order has been canceled and the order owner has been notified.`);
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

            {tickets && <AttendeeList order={order} tickets={tickets}/>}

            <Alert className={classes.alert} variant="light" color="blue" title={t`Please Note`}
                   icon={<IconInfoCircle/>}>
                {t`Canceling will cancel all tickets associated with this order, and release the tickets back into the available pool.`}
            </Alert>

            <Button loading={cancelOrderMutation.isLoading} className={'mb20'} color={'red'} fullWidth
                    onClick={handleCancelOrder}>
                {t`Cancel Order`}
            </Button>
        </Modal>
    )
};