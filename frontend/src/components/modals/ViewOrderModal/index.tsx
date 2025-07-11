import { GenericModalProps, IdParam, Product, QuestionAnswer } from "../../../types.ts";
import { useParams } from "react-router-dom";
import { useGetEvent } from "../../../queries/useGetEvent.ts";
import { useGetOrder } from "../../../queries/useGetOrder.ts";
import { OrderSummary } from "../../common/OrderSummary";
import { Modal } from "../../common/Modal";
import { AttendeeList } from "../../common/AttendeeList";
import { OrderDetails } from "../../common/OrderDetails";
import { t } from "@lingui/macro";
import { QuestionAndAnswerList } from "../../common/QuestionAndAnswerList";
import { Stack, Text } from "@mantine/core";
import {
    IconInfoCircle,
    IconQuestionMark,
    IconReceipt,
    IconUsers
} from "@tabler/icons-react";
import { OrderStatusBadge } from "../../common/OrderStatusBadge";
import { Accordion, AccordionItem } from "../../common/Accordion";
import classes from './ViewOrderModal.module.scss';

interface ViewOrderModalProps {
    orderId: IdParam;
}

export const ViewOrderModal = ({ onClose, orderId }: GenericModalProps & ViewOrderModalProps) => {
    const { eventId } = useParams();
    const { data: order } = useGetOrder(eventId, orderId);
    const { data: event, data: { product_categories: productCategories } = {} } = useGetEvent(eventId);
    const products = productCategories?.flatMap(category => category.products);
    const orderHasQuestions = order?.question_answers && order.question_answers.length > 0;
    const orderHasAttendees = order?.attendees && order.attendees.length > 0;

    if (!order || !event) {
        return null;
    }

    const accordionItems: AccordionItem[] = [
        {
            value: 'details',
            icon: IconInfoCircle,
            title: t`Order Details`,
            content: <OrderDetails order={order} event={event} cardVariant="noStyle" />
        },
        {
            value: 'summary',
            icon: IconReceipt,
            title: t`Order Summary`,
            content: <OrderSummary event={event} order={order} />
        },
        {
            value: 'questions',
            icon: IconQuestionMark,
            title: t`Questions & Answers`,
            count: orderHasQuestions ? order.question_answers.length : undefined,
            content: orderHasQuestions ? (
                <QuestionAndAnswerList questionAnswers={order.question_answers as QuestionAnswer[]} />
            ) : (
                <Text c="dimmed" ta="center" py="xl">
                    {t`No questions have been asked for this order.`}
                </Text>
            )
        },
        {
            value: 'attendees',
            icon: IconUsers,
            title: t`Attendees`,
            count: orderHasAttendees ? order.attendees.length : undefined,
            content: orderHasAttendees ? (
                <AttendeeList order={order} products={products as Product[]} />
            ) : (
                <Text c="dimmed" ta="center" py="xl">
                    {t`No attendees have been added to this order.`}
                </Text>
            )
        }
    ];

    return (
        <Modal
            opened={true}
            onClose={onClose}
            size="lg"
            padding="md"
        >
            <Stack className={classes.container}>
                <div className={classes.header}>
                    <div className={classes.orderInfo}>
                        <Text fz="sm" c="dimmed" mb={4}>Order Reference</Text>
                        <Text fz="xl" fw={600}>{order.public_id}</Text>
                    </div>
                    <OrderStatusBadge order={order} variant="outline" size="md" />
                </div>

                <Accordion
                    items={accordionItems}
                    defaultValue="details"
                />
            </Stack>
        </Modal>
    );
};
