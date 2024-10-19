import {GenericModalProps, IdParam,} from "../../../types.ts";
import {useParams} from "react-router-dom";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {OrderSummary} from "../../common/OrderSummary";
import {Modal} from "../../common/Modal";
import {AttendeeList} from "../../common/AttendeeList";
import {OrderDetails} from "../../common/OrderDetails";
import {Trans} from "@lingui/macro";
import {QuestionAndAnswerList} from "../../common/QuestionAndAnswerList";
import {rem, Tabs} from "@mantine/core";
import {IconQuestionMark, IconReceipt, IconUsers} from "@tabler/icons-react";
import {Card} from "../../common/Card";
import {OrderStatusBadge} from "../../common/OrderStatusBadge";

interface ViewOrderModalProps {
    orderId: IdParam,
}

export const ViewOrderModal = ({onClose, orderId}: GenericModalProps & ViewOrderModalProps) => {
    const {eventId} = useParams();
    const {data: order} = useGetOrder(eventId, orderId);
    const {data: event, data: {product_categories: productCategories} = {}} = useGetEvent(eventId);
    const products = productCategories?.flatMap(category => category.products);

    console.log(event);

    if (!order || !event) {
        return null;
    }
    const iconStyle = {width: rem(12), height: rem(12)};

    return (
        <Modal
            opened={true}
            onClose={onClose}
        >
            <h1 style={{margin: 0, marginLeft: '20px'}}>
                <Trans>Order: {order.public_id}</Trans>
                <div>
                    <OrderStatusBadge order={order} variant={'outline'}/>
                </div>
            </h1>

            <OrderDetails order={order} event={event} cardVariant={'noStyle'}/>

            <Tabs defaultValue="summary">
                <Tabs.List grow>
                    <Tabs.Tab value="summary" leftSection={<IconReceipt style={iconStyle}/>}>
                        Summary
                    </Tabs.Tab>
                    <Tabs.Tab value="questions" leftSection={<IconQuestionMark style={iconStyle}/>}>
                        Questions
                    </Tabs.Tab>
                    <Tabs.Tab value="attendees" leftSection={<IconUsers style={iconStyle}/>}>
                        Attendees
                    </Tabs.Tab>
                </Tabs.List>

                <Card variant={'noStyle'} style={{padding: rem(20), borderTop: 0, borderRadius: 0}}>
                    <Tabs.Panel value="summary">
                        <OrderSummary event={event} order={order}/>
                    </Tabs.Panel>

                    <Tabs.Panel value="questions">
                        {(order.question_answers && order.question_answers.length > 0)
                            && (
                                <>
                                    <QuestionAndAnswerList questionAnswers={order.question_answers}/>
                                </>
                            )}
                    </Tabs.Panel>

                    <Tabs.Panel value="attendees">
                        {products && (
                            <>
                                <AttendeeList order={order} products={products}/>
                            </>
                        )}
                    </Tabs.Panel>
                </Card>

            </Tabs>
        </Modal>
    )
};
