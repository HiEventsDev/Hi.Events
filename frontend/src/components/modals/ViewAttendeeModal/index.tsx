import {useParams} from "react-router-dom";
import {useGetAttendee} from "../../../queries/useGetAttendee.ts";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {GenericModalProps, IdParam} from "../../../types.ts";
import {OrderDetails} from "../../common/OrderDetails";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {AttendeeDetails} from "../../common/AttendeeDetails";
import {QuestionAndAnswerList} from "../../common/QuestionAndAnswerList";
import {LoadingMask} from "../../common/LoadingMask";
import {AttendeeTicket} from "../../common/AttendeeTicket";

interface ViewAttendeeModalProps extends GenericModalProps {
    onClose: () => void;
    attendeeId: IdParam;
}

export const ViewAttendeeModal = ({onClose, attendeeId}: ViewAttendeeModalProps) => {
    const {eventId} = useParams();

    const attendee = useGetAttendee(eventId, attendeeId).data;
    const order = useGetOrder(eventId, attendee?.order_id).data;
    const event = useGetEvent(eventId).data;

    if (!attendee || !order || !event) {
        return <LoadingMask/>
    }

    return (
        <Modal
            opened
            onClose={onClose}
            withCloseButton
            heading={attendee ? `${attendee.first_name} ${attendee.last_name}` : ''}
        >

            <h3>{t`Attendee Details`}</h3>
            {(attendee && event) && <AttendeeDetails attendee={attendee}/>}

            <h3>{t`Order Details`}</h3>
            {(order && event) && <OrderDetails order={order} event={event}/>}

            {(attendee.question_answers && attendee.question_answers.length > 0)
                && (
                    <>
                        <h3>
                            {t`Questions`}
                        </h3>
                        <QuestionAndAnswerList questionAnswers={attendee.question_answers}/>
                    </>
                )}

            <h3>
                {t`Ticket`}
            </h3>

            {(attendee?.ticket) && <AttendeeTicket event={event} attendee={attendee} ticket={attendee.ticket}/>}
        </Modal>
    )
}