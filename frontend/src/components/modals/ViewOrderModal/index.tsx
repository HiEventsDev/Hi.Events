import {GenericModalProps, IdParam,} from "../../../types.ts";
import {useParams} from "react-router-dom";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {OrderSummary} from "../../common/OrderSummary";
import {Modal} from "../../common/Modal";
import {Card} from "../../common/Card";
import {AttendeeList} from "../../common/AttendeeList";
import {OrderDetails} from "../../common/OrderDetails";
import {t, Trans} from "@lingui/macro";
import {QuestionAndAnswerList} from "../../common/QuestionAndAnswerList";

interface ViewOrderModalProps {
    orderId: IdParam,
}

export const ViewOrderModal = ({onClose, orderId}: GenericModalProps & ViewOrderModalProps) => {
    const {eventId} = useParams();
    const {data: order} = useGetOrder(eventId, orderId);
    const {data: event, data: {tickets} = {}} = useGetEvent(eventId);

    if (!order || !event) {
        return null;
    }

    return (
        <Modal
            opened={true}
            onClose={onClose}
            heading={<Trans>Order Details {order.public_id}</Trans>}
        >
            <OrderDetails order={order} event={event}/>

            <h3>
                {t`Order Summary`}
            </h3>
            <Card variant={'lightGray'}>
                <OrderSummary event={event} order={order}/>
            </Card>

            {(order.question_answers && order.question_answers.length > 0)
                && (
                    <>
                        <h3>
                            {t`Questions`}
                        </h3>
                        <QuestionAndAnswerList questionAnswers={order.question_answers}/>
                    </>
                )}

            {tickets && (
                <>
                    <h3>
                        {t`Attendees`}
                    </h3>
                    <AttendeeList order={order} tickets={tickets}/>
                </>
            )}
        </Modal>
    )
};