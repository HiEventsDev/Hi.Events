import {GenericModalProps, IdParam, Product, QuestionAnswer} from "../../../types.ts";
import {useParams} from "react-router";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {OrderSummary} from "../../common/OrderSummary";
import {AttendeeList} from "../../common/AttendeeList";
import {t} from "@lingui/macro";
import {QuestionAndAnswerList} from "../../common/QuestionAndAnswerList";
import {ActionIcon, Anchor, Avatar, Button, CopyButton, Text, Textarea, TextInput, Tooltip} from "@mantine/core";
import {IconCheck, IconCopy} from "@tabler/icons-react";
import {EntityActionBar} from "../../common/EntityActions";
import {useOrderActions} from "../../../hooks/useOrderActions.tsx";
import {OrderStatusBadge} from "../../common/OrderStatusBadge";
import {useForm} from "@mantine/form";
import {useEffect, useState} from "react";
import {useEditOrder} from "../../../mutations/useEditOrder";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler";
import {showSuccess} from "../../../utilites/notifications";
import {InputGroup} from "../../common/InputGroup";
import {InputLabelWithHelp} from "../../common/InputLabelWithHelp";
import {getInitials} from "../../../utilites/helpers.ts";
import {Currency} from "../../common/Currency";
import {formatDateWithLocale, prettyDate, relativeDate} from "../../../utilites/dates.ts";
import {formatAddress} from "../../../utilites/addressUtilities.ts";
import {capitalize} from "../../../utilites/stringHelper.ts";
import classes from './ManageOrderModal.module.scss';
import {EditOrderPayload} from "../../../api/order.client.ts";
import {
    DrawerStat,
    SideDrawer,
    SideDrawerFields,
    SideDrawerHeading,
    SideDrawerSection,
    SideDrawerStats
} from "../../common/SideDrawer";

interface ManageOrderModalProps {
    orderId: IdParam;
}

const FORM_ID = 'manage-order-form';

export const ManageOrderModal = ({onClose, orderId}: GenericModalProps & ManageOrderModalProps) => {
    const {eventId} = useParams();
    const {data: order, refetch: refetchOrder} = useGetOrder(eventId, orderId);
    const {data: event, data: {product_categories: productCategories} = {}} = useGetEvent(eventId);
    const products = productCategories?.flatMap(category => category.products);
    const [isEditing, setIsEditing] = useState(false);
    const errorHandler = useFormErrorResponseHandler();
    const mutation = useEditOrder();
    const {getOrderActions, orderActionModals} = useOrderActions({
        eventId,
        onEdit: () => setIsEditing(true),
    });

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
        return <SideDrawer opened={true} onClose={onClose} loading/>;
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
                    setIsEditing(false);
                },
                onError: (error) => errorHandler(form, error),
            }
        );
    };

    const handleCancelEdit = () => {
        form.reset();
        setIsEditing(false);
    };

    const buyerName = `${order.first_name} ${order.last_name}`;
    const questionAnswers = order.question_answers ?? [];
    const attendees = order.attendees ?? [];
    const occurrences = Array.from(
        new Map((order.order_items ?? [])
            .filter(item => item.event_occurrence)
            .map(item => [item.event_occurrence!.id, item.event_occurrence!])).values()
    );

    const stats: DrawerStat[] = [
        {
            label: t`Total`,
            value: <Currency currency={order.currency} price={order.total_gross}/>,
        },
        {
            label: t`Items`,
            value: (order.order_items ?? []).reduce((total, item) => total + item.quantity, 0),
        },
        {
            label: t`Status`,
            value: <OrderStatusBadge order={order} variant="light"/>,
        },
        {
            label: t`Placed`,
            value: (
                <Tooltip label={prettyDate(order.created_at, event.timezone)} position="bottom" withArrow>
                    <span>{relativeDate(order.created_at)}</span>
                </Tooltip>
            ),
        },
    ];

    const fields: DrawerStat[] = [
        {
            label: t`Email`,
            value: <Anchor href={'mailto:' + order.email} target="_blank">{order.email}</Anchor>,
        },
        ...(occurrences.length > 0 ? [{
            label: occurrences.length === 1 ? t`Occurrence` : t`Occurrences`,
            value: occurrences.map(occurrence => (
                <div key={occurrence.id}>
                    {formatDateWithLocale(occurrence.start_date, 'shortDate', event.timezone)}
                    {' '}
                    {formatDateWithLocale(occurrence.start_date, 'timeOnly', event.timezone)}
                    {occurrence.label && ` · ${occurrence.label}`}
                </div>
            )),
        }] : []),
        ...(order.payment_provider ? [{
            label: t`Payment provider`,
            value: capitalize(order.payment_provider),
        }] : []),
        ...(order.promo_code ? [{
            label: t`Promo code`,
            value: order.promo_code,
        }] : []),
        ...(order.total_refunded > 0 ? [{
            label: t`Total refunded`,
            value: <Currency currency={order.currency} price={order.total_refunded}/>,
        }] : []),
        ...(order.address ? [{
            label: t`Address`,
            value: formatAddress(order.address),
        }] : []),
    ];

    const header = (
        <SideDrawerHeading
            media={(
                <Avatar size={42} radius="xl" variant="light" color="primary">
                    {getInitials(buyerName)}
                </Avatar>
            )}
            title={(
                <>
                    <span className={classes.reference}>{order.public_id}</span>
                    <CopyButton value={String(order.public_id)}>
                        {({copied, copy}) => (
                            <Tooltip label={copied ? t`Copied` : t`Copy`} position="bottom" withArrow>
                                <ActionIcon
                                    variant="subtle"
                                    color="gray"
                                    size="sm"
                                    radius="xl"
                                    aria-label={t`Copy`}
                                    onClick={copy}
                                >
                                    {copied ? <IconCheck size={14}/> : <IconCopy size={14}/>}
                                </ActionIcon>
                            </Tooltip>
                        )}
                    </CopyButton>
                </>
            )}
            subtitle={(
                <>
                    <span>{buyerName}</span>
                    <span className={classes.separator}>·</span>
                    <span className={classes.email}>{order.email}</span>
                </>
            )}
        />
    );

    const footer = isEditing && (
        <>
            <Button variant="default" onClick={handleCancelEdit}>
                {t`Cancel`}
            </Button>
            <Button type="submit" form={FORM_ID} loading={mutation.isPending}>
                {t`Save Changes`}
            </Button>
        </>
    );

    return (
        <SideDrawer
            opened={true}
            onClose={onClose}
            header={header}
            footer={footer}
        >
            {isEditing ? (
                <form id={FORM_ID} onSubmit={form.onSubmit(handleSubmit)} className={classes.form}>
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
                </form>
            ) : (
                <>
                    <SideDrawerStats stats={stats}/>

                    <div className={classes.actions}>
                        <EntityActionBar actions={getOrderActions(order)}/>
                    </div>

                    <SideDrawerSection title={t`Details`}>
                        <SideDrawerFields fields={fields}/>
                    </SideDrawerSection>

                    <SideDrawerSection title={t`Order Summary`} surface>
                        <OrderSummary event={event} order={order}/>
                    </SideDrawerSection>

                    {order.notes && (
                        <SideDrawerSection title={t`Notes`}>
                            <Text size="sm" className={classes.notes}>{order.notes}</Text>
                        </SideDrawerSection>
                    )}

                    {attendees.length > 0 && (
                        <SideDrawerSection title={t`Attendees`} count={attendees.length}>
                            <AttendeeList
                                refetchOrder={refetchOrder}
                                order={order}
                                products={products as Product[]}
                                questionAnswers={questionAnswers}
                            />
                        </SideDrawerSection>
                    )}

                    {questionAnswers.length > 0 && (
                        <SideDrawerSection title={t`Questions & Answers`} count={questionAnswers.length}>
                            <QuestionAndAnswerList
                                onEditAnswer={refetchOrder}
                                questionAnswers={questionAnswers as QuestionAnswer[]}
                            />
                        </SideDrawerSection>
                    )}
                </>
            )}

            {orderActionModals}
        </SideDrawer>
    );
};
