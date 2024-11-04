import {useParams} from "react-router-dom";
import {useGetAttendee} from "../../../queries/useGetAttendee.ts";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {GenericModalProps, IdParam, QuestionAnswer} from "../../../types.ts";
import {OrderDetails} from "../../common/OrderDetails";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {AttendeeDetails} from "../../common/AttendeeDetails";
import {QuestionAndAnswerList} from "../../common/QuestionAndAnswerList";
import {LoadingMask} from "../../common/LoadingMask";
import {AttendeeProduct} from "../../common/AttendeeProduct";
import {Avatar, Group, Stack, Text} from "@mantine/core";
import {IconQuestionMark, IconReceipt, IconTicket, IconUser} from "@tabler/icons-react";
import {getInitials} from "../../../utilites/helpers.ts";
import {Accordion, AccordionItem} from "../../common/Accordion";
import classes from './ViewAttendeeModal.module.scss';

interface ViewAttendeeModalProps extends GenericModalProps {
    onClose: () => void;
    attendeeId: IdParam;
}

export const ViewAttendeeModal = ({onClose, attendeeId}: ViewAttendeeModalProps) => {
    const {eventId} = useParams();
    const {data: attendee} = useGetAttendee(eventId, attendeeId);
    const {data: order} = useGetOrder(eventId, attendee?.order_id);
    const {data: event} = useGetEvent(eventId);

    if (!attendee || !order || !event) {
        return <LoadingMask/>;
    }

    const fullName = `${attendee.first_name} ${attendee.last_name}`;
    const hasQuestions = attendee.question_answers && attendee.question_answers.length > 0;

    const accordionItems: AccordionItem[] = [
        {
            value: 'details',
            icon: IconUser,
            title: t`Attendee Details`,
            content: <AttendeeDetails attendee={attendee}/>
        },
        {
            value: 'order',
            icon: IconReceipt,
            title: t`Order Details`,
            content: <OrderDetails order={order} event={event} cardVariant="noStyle"/>
        },
        {
            value: 'ticket',
            icon: IconTicket,
            title: t`Attendee Ticket`,
            content: attendee.product ? (
                <AttendeeProduct
                    event={event}
                    attendee={attendee}
                    product={attendee.product}
                />
            ) : (
                <Text c="dimmed" ta="center" py="xl">
                    {t`No product associated with this attendee.`}
                </Text>
            )
        },
        {
            value: 'questions',
            icon: IconQuestionMark,
            title: t`Questions & Answers`,
            count: hasQuestions ? attendee?.question_answers?.length : undefined,
            content: hasQuestions ? (
                <QuestionAndAnswerList questionAnswers={attendee.question_answers as QuestionAnswer[]}/>
            ) : (
                <Text c="dimmed" ta="center" py="xl">
                    {t`No questions have been answered by this attendee.`}
                </Text>
            )
        }
    ];

    return (
        <Modal
            opened
            onClose={onClose}
            size="lg"
            padding="md"
        >
            <Stack className={classes.container}>
                <div className={classes.header}>
                    <Group gap="md">
                        <Avatar
                            size="lg"
                            radius="xl"
                        >
                            {getInitials(fullName)}
                        </Avatar>
                        <div className={classes.attendeeInfo}>
                            <Text fz="xs" c="dimmed">Attendee</Text>
                            <Text fz="xl" fw={600}>{fullName}</Text>
                        </div>
                    </Group>
                </div>

                <Accordion
                    items={accordionItems}
                    defaultValue="details"
                />
            </Stack>
        </Modal>
    );
};
