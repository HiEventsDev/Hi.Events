import {GenericModalProps, IdParam, Product, QuestionAnswer} from "../../../types.ts";
import {useParams} from "react-router";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {OrderSummary} from "../../common/OrderSummary";
import {AttendeeList} from "../../common/AttendeeList";
import {OrderDetails} from "../../common/OrderDetails";
import {t} from "@lingui/macro";
import {QuestionAndAnswerList} from "../../common/QuestionAndAnswerList";
import {Box, Stack, Tabs, Text, Textarea, TextInput} from "@mantine/core";
import {IconEdit, IconInfoCircle, IconNotebook, IconQuestionMark, IconReceipt, IconUsers} from "@tabler/icons-react";
import {OrderStatusBadge} from "../../common/OrderStatusBadge";
import {Accordion, AccordionItem} from "../../common/Accordion";
import {useForm} from "@mantine/form";
import {useEffect, useState} from "react";
import {useEditOrder} from "../../../mutations/useEditOrder";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler";
import {showSuccess} from "../../../utilites/notifications";
import {Button} from "../../common/Button";
import {InputGroup} from "../../common/InputGroup";
import {InputLabelWithHelp} from "../../common/InputLabelWithHelp";
import classes from './ManageOrderModal.module.scss';
import {EditOrderPayload} from "../../../api/order.client.ts";
import {SideDrawer} from "../../common/SideDrawer";

interface ManageOrderModalProps {
    orderId: IdParam;
}

export const ManageOrderModal = ({onClose, orderId}: GenericModalProps & ManageOrderModalProps) => {
    const {eventId} = useParams();
    const {data: order, refetch: refetchOrder} = useGetOrder(eventId, orderId);
    const {data: event, data: {product_categories: productCategories} = {}} = useGetEvent(eventId);
    const products = productCategories?.flatMap(category => category.products);
    const orderHasQuestions = order?.question_answers && order.question_answers.length > 0;
    const orderHasAttendees = order?.attendees && order.attendees.length > 0;
    const [activeTab, setActiveTab] = useState("view");
    const errorHandler = useFormErrorResponseHandler();
    const mutation = useEditOrder();

    const form = useForm({
        initialValues: {
            first_name: "",
            last_name: "",
            email: "",
            notes: "",
        },
    });

    useEffect(() => {
        if (order) {
            form.initialize({
                first_name: order.first_name,
                last_name: order.last_name,
                email: order.email,
                notes: order.notes || "",
            });
        }
    }, [order]);

    if (!order || !event) {
        return null;
    }

    const handleSubmit = (values: EditOrderPayload) => {
        mutation.mutate(
            {
                eventId,
                orderId,
                payload: values,
            },
            {
                onSuccess: () => {
                    showSuccess(t`Successfully updated order`);
                    setActiveTab("view");
                },
                onError: (error) => errorHandler(form, error),
            }
        );
    };

    const accordionItems: AccordionItem[] = [
        {
            value: 'details',
            icon: IconInfoCircle,
            title: t`Order Details`,
            content: <OrderDetails order={order} event={event} cardVariant="noStyle" style={{padding: 0}}/>
        },
        {
            value: "notes",
            icon: IconNotebook,
            title: t`Order Notes`,
            hidden: !order.notes,
            content: (
                <Box p="md">
                    <Text style={{whiteSpace: 'pre-line'}}>
                        {order.notes}
                    </Text>
                </Box>
            ),
        },
        {
            value: 'summary',
            icon: IconReceipt,
            title: t`Order Summary`,
            content: <OrderSummary event={event} order={order}/>
        },
        {
            value: 'questions',
            icon: IconQuestionMark,
            title: t`Questions & Answers`,
            count: orderHasQuestions ? order.question_answers.length : undefined,
            content: orderHasQuestions ? (
                <QuestionAndAnswerList
                    onEditAnswer={refetchOrder}
                    questionAnswers={order.question_answers as QuestionAnswer[]}/>
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
                <AttendeeList refetchOrder={refetchOrder} order={order} products={products as Product[]} questionAnswers={order.question_answers}/>
            ) : (
                <Text c="dimmed" ta="center" py="xl">
                    {t`No attendees have been added to this order.`}
                </Text>
            )
        }
    ];

    const editContent = (
        <form onSubmit={form.onSubmit(handleSubmit)}>
            <Stack gap="xs">
                <InputGroup>
                    <TextInput
                        {...form.getInputProps("first_name")}
                        label={t`First name`}
                        placeholder={t`Homer`}
                        required
                    />
                    <TextInput
                        {...form.getInputProps("last_name")}
                        label={t`Last name`}
                        placeholder={t`Simpson`}
                        required
                    />
                </InputGroup>
                <TextInput
                    {...form.getInputProps("email")}
                    label={t`Email address`}
                    placeholder="homer@simpson.com"
                    required
                />
                <Textarea
                    label={
                        <InputLabelWithHelp
                            label={t`Notes`}
                            helpText={t`Add any notes about the order. These will not be visible to the customer.`}
                        />
                    }
                    {...form.getInputProps("notes")}
                    placeholder={t`Add any notes about the order...`}
                    minRows={3}
                    maxRows={6}
                    autosize
                />
                <Button type="submit" fullWidth disabled={mutation.isPending}>
                    {mutation.isPending ? t`Working...` : t`Save Changes`}
                </Button>
            </Stack>
        </form>
    );

    return (
        <SideDrawer
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
                    <OrderStatusBadge order={order} variant="outline"/>
                </div>

                <Tabs value={activeTab} onChange={setActiveTab as any}>
                    <Tabs.List>
                        <Tabs.Tab value="view" leftSection={<IconInfoCircle size={16}/>}>
                            {t`View`}
                        </Tabs.Tab>
                        <Tabs.Tab value="edit" leftSection={<IconEdit size={16}/>}>
                            {t`Edit`}
                        </Tabs.Tab>
                    </Tabs.List>

                    <Box mt="md">
                        <Tabs.Panel value="view">
                            <Accordion
                                items={accordionItems}
                                defaultValue="details"
                            />
                        </Tabs.Panel>
                        <Tabs.Panel value="edit">
                            {editContent}
                        </Tabs.Panel>
                    </Box>
                </Tabs>
            </Stack>
        </SideDrawer>
    );
};
