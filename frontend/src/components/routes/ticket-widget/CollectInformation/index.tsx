import {useMutation} from "@tanstack/react-query";
import {FinaliseOrderPayload, orderClientPublic} from "../../../../api/order.client.ts";
import {NavLink, useNavigate, useParams} from "react-router-dom";
import {Alert, Button, Group, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {notifications} from "@mantine/notifications";
import {useGetOrderPublic} from "../../../../queries/useGetOrderPublic.ts";
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {useGetEventQuestionsPublic} from "../../../../queries/useGetEventQuestionsPublic.ts";
import {CheckoutOrderQuestions, CheckoutTicketQuestions} from "../../../common/CheckoutQuestion";
import {Question} from "../../../../types.ts";
import {useEffect} from "react";
import {t} from "@lingui/macro";
import {InputGroup} from "../../../common/InputGroup";
import {Center} from "../../../common/Center";
import {Card} from "../../../common/Card";
import {IconCopy} from "@tabler/icons-react";

export const CollectInformation = () => {
    const {eventId, eventSlug, orderShortId} = useParams();
    const navigate = useNavigate();
    const {
        isFetched: isOrderFetched,
        data: order,
        data: {order_items: orderItems} = {},
        isError: isOrderError,
    } = useGetOrderPublic(eventId, orderShortId);
    const {
        data: event,
        data: {tickets} = {},
        isFetched: isEventFetched,
        isError: isEventError,
    } = useGetEventPublic(eventId, isOrderFetched, !!order?.promo_code, order?.promo_code ?? null);
    const questionsQuery = useGetEventQuestionsPublic(eventId);
    const ticketQuestions = questionsQuery?.data?.filter(question => question.belongs_to === "TICKET");
    const orderQuestions = questionsQuery?.data?.filter(question => question.belongs_to === "ORDER");
    let attendeeIndex = 0;

    const form = useForm({
        initialValues: {
            order: {
                first_name: "",
                last_name: "",
                email: "",
                address: {},
                questions: {},
            },
            attendees: [{
                first_name: "",
                last_name: "",
                email: "",
                ticket_price_id: "",
                ticket_id: "",
                questions: {},
            }],
        },
    });

    const copyDetailsToAllAttendees = () => {
        const updatedAttendees = form.values.attendees.map((attendee) => {
            return {
                ...attendee,
                first_name: form.values.order.first_name,
                last_name: form.values.order.last_name,
                email: form.values.order.email,
            };
        });

        form.setValues({
            ...form.values,
            attendees: updatedAttendees,
        });
    }

    const mutation = useMutation(
        (orderData: FinaliseOrderPayload) => orderClientPublic.finaliseOrder(Number(eventId), String(orderShortId), orderData),
        {
            onSuccess: (data) => {
                const nextPage = order?.is_payment_required ? 'payment' : 'summary';
                navigate('/checkout/' + eventId + '/' + data.data.short_id + '/' + nextPage);
            },
            onError: (error: any) => {
                if (error?.response?.data?.errors && Object.keys(error?.response?.data?.errors).length > 0) {
                    form.setErrors(error.response.data.errors);
                } else if (error?.response?.data?.message) {
                    notifications.show({
                        message: error?.response?.data?.message,
                    });

                    // if it's a 409, we need to redirect to the event page as the order is no longer valid
                    if (error.response.status === 409) {
                        navigate(`/event/${eventId}/${event?.slug}`);
                    }
                }
            },
        }
    );

    const createTicketIdToQuestionMap = () => {
        const ticketIdToQuestionMap = new Map();

        ticketQuestions?.forEach(question => {
            question.ticket_ids?.forEach(id => {
                const existingQuestions = ticketIdToQuestionMap.get(id);
                ticketIdToQuestionMap.set(
                    id,
                    existingQuestions ? [...existingQuestions, question] : [question]
                );
            });
        });

        return ticketIdToQuestionMap;
    }

    const createAttendeesAndQuestions = (ticketIdToQuestionMap: Map<number, Question[]>) => {
        const attendees: any = [];

        orderItems?.forEach(orderItem => {
            Array.from(Array(orderItem?.quantity)).map(() => {
                attendees.push({
                    ticket_price_id: orderItem?.ticket_price_id,
                    ticket_id: orderItem?.ticket_id,
                    first_name: "",
                    last_name: "",
                    email: "",
                    questions: ticketIdToQuestionMap.get(orderItem?.ticket_id)?.map((question: Question) => {
                        return {
                            question_id: question.id,
                            response: {},
                        }
                    })
                });
            });
        });

        return attendees;
    }

    const createFormOrderQuestions = () => {
        const formOrderQuestions: any = [];

        orderQuestions?.forEach(orderQuestion => {
            formOrderQuestions.push({
                question_id: orderQuestion.id,
                response: {},
            });
        });

        return formOrderQuestions;
    }

    const handleSubmit = (values: any) => {
        mutation.mutate(values);
    };

    useEffect(() => {
        if (isEventFetched && isOrderFetched) {
            const attendees = createAttendeesAndQuestions(createTicketIdToQuestionMap());

            form.setValues({
                ...form.values,
                attendees,
            });
        }
    }, [isEventFetched, isOrderFetched]);

    useEffect(() => {
        if (questionsQuery.isFetched) {
            const formOrderQuestions = createFormOrderQuestions();

            form.setValues({
                ...form.values,
                order: {
                    ...form.values.order,
                    questions: formOrderQuestions,
                },
            });
        }
    }, [questionsQuery.isFetched]);

    if (!isEventFetched || !isOrderFetched) {
        return <></>;
    }

    if (order?.payment_status === 'AWAITING_PAYMENT') {
        return (
            <Center>
                {t` This order is awaiting payment.`} <NavLink
                to={`/checkout/${eventId}/${orderShortId}/payment`}>
                {t`Complete payment`}
            </NavLink>
            </Center>
        );
    }

    if (order?.status === 'COMPLETED') {
        return (
            <Center>
                {t`This order has already been processed.`} <NavLink
                to={`/checkout/${eventId}/${orderShortId}/summary`}>
                {t`View order details`}
            </NavLink>
            </Center>
        );
    }

    if (order?.status === 'CANCELLED') {
        return (
            <Center>
                {t`This order has been cancelled.`} <NavLink
                to={`/event/${eventId}/${eventSlug}`}>
                {t`Back to event homepage`}
            </NavLink>
            </Center>
        );
    }

    if (order?.is_expired) {
        navigate(`/event/${eventId}/${eventSlug}`);
    }

    if (isOrderError || isEventError || questionsQuery.isError) {
        //todo - we need to decide what to do here
        return (
            <Alert>
                {t`There was an error loading this content. Please refresh the page and try again.`}
            </Alert>
        );
    }

    return (
        <>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <h2>
                    {t`Your Details`}
                </h2>

                <Card>
                    <InputGroup>
                        <TextInput
                            withAsterisk
                            label={t`First Name`}
                            placeholder={t`First name`}
                            {...form.getInputProps("order.first_name")}
                        />
                        <TextInput
                            withAsterisk
                            label={t`Last Name`}
                            placeholder={t`Last Name`}
                            {...form.getInputProps("order.last_name")}
                        />
                    </InputGroup>

                    <TextInput
                        withAsterisk
                        type={"email"}
                        label={t`Email Address`}
                        placeholder={t`Email Address`}
                        {...form.getInputProps("order.email")}
                    />

                    {orderQuestions && <CheckoutOrderQuestions form={form} questions={orderQuestions}/>}

                    <Button p={0} ml={0} size={'sm'} variant={'transparent'} leftSection={<IconCopy size={14}/>}
                            onClick={copyDetailsToAllAttendees}>
                        {t`Copy details to all attendees`}
                    </Button>
                </Card>

                {orderItems?.map(orderItem => {
                    const ticket = tickets?.find(ticket => ticket.id === orderItem.ticket_id);

                    if (!ticket) {
                        return;
                    }

                    return (
                        <div key={orderItem.ticket_id + orderItem.id}>
                            <h3>{orderItem?.item_name}</h3>
                            {Array.from(Array(orderItem?.quantity)).map((_, index) => {
                                const attendeeInputs = (
                                    <Card>
                                        <h4 style={{marginTop: 0}}>
                                            {t`Attendee`} {index + 1} {t`Details`}
                                        </h4>
                                        <InputGroup>
                                            <TextInput
                                                withAsterisk
                                                label={t`First Name`}
                                                placeholder={t`First name`}
                                                {...form.getInputProps(`attendees.${attendeeIndex}.first_name`)}
                                            />
                                            <TextInput
                                                withAsterisk
                                                label={t`Last Name`}
                                                placeholder={t`Last Name`}
                                                {...form.getInputProps(`attendees.${attendeeIndex}.last_name`)}
                                            />
                                        </InputGroup>

                                        <TextInput
                                            withAsterisk
                                            label={t`Email Address`}
                                            placeholder={t`Email Address`}
                                            {...form.getInputProps(`attendees.${attendeeIndex}.email`)}
                                        />

                                        {ticketQuestions &&
                                            <CheckoutTicketQuestions
                                                index={attendeeIndex}
                                                ticket={ticket}
                                                form={form}
                                                questions={ticketQuestions}/>}
                                    </Card>
                                );

                                attendeeIndex++;

                                return attendeeInputs;
                            })}
                        </div>
                    );
                })}

                {!!event?.settings?.pre_checkout_message && (
                    <Card>
                        <div dangerouslySetInnerHTML={{__html: event?.settings?.pre_checkout_message}}/>
                    </Card>
                )}

                <Group mt="xl">
                    <Button fullWidth loading={mutation.isLoading} type="submit" size="md">
                        {order?.is_payment_required ? t`Continue To Payment` : t`Complete Order`}
                    </Button>
                </Group>
            </form>
        </>
    );
}

export default CollectInformation;