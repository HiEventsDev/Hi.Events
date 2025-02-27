import {TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {CustomSelect, ItemProps} from "../../common/CustomSelect";
import {IconBolt, IconWebhook, IconWebhookOff} from "@tabler/icons-react";

interface WebhookFormProps {
    form: UseFormReturnType<{
        url: string;
        event_types: string[];
        status: 'ENABLED' | 'PAUSED';
    }>;
}

export const WebhookForm = ({form}: WebhookFormProps) => {
    const statusOptions: ItemProps[] = [
        {
            icon: <IconWebhook/>,
            label: t`Enabled`,
            value: 'ENABLED',
            description: t`Webhook will send notifications`,
        },
        {
            icon: <IconWebhookOff/>,
            label: t`Paused`,
            value: 'PAUSED',
            description: t`Webhook will not send notifications`,
        },
    ];

    const eventTypeOptions: ItemProps[] = [
        {
            icon: <IconBolt size={14}/>,
            label: t`Product Created`,
            value: 'product.created',
            description: t`When a new product is created`,
        },
        {
            icon: <IconBolt size={14}/>,
            label: t`Product Updated`,
            value: 'product.updated',
            description: t`When a product is updated`,
        },
        {
            icon: <IconBolt size={14}/>,
            label: t`Product Deleted`,
            value: 'product.deleted',
            description: t`When a product is deleted`,
        },
        {
            icon: <IconBolt size={14}/>,
            label: t`Order Created`,
            value: 'order.created',
            description: t`When a new order is created`,
        },
        {
            icon: <IconBolt size={14}/>,
            label: t`Order Updated`,
            value: 'order.updated',
            description: t`When an order is updated`,
        },
        {
            icon: <IconBolt size={14}/>,
            label: t`Order Marked as Paid`,
            value: 'order.marked_as_paid',
            description: t`When an order is marked as paid`,
        },
        {
            icon: <IconBolt size={14}/>,
            label: t`Order Refunded`,
            value: 'order.refunded',
            description: t`When an order is refunded`,
        },
        {
            icon: <IconBolt size={14}/>,
            label: t`Order Cancelled`,
            value: 'order.cancelled',
            description: t`When an order is cancelled`,
        },
        {
            icon: <IconBolt size={14}/>,
            label: t`Attendee Created`,
            value: 'attendee.created',
            description: t`When a new attendee is created`,
        },
        {
            icon: <IconBolt size={14}/>,
            label: t`Attendee Updated`,
            value: 'attendee.updated',
            description: t`When an attendee is updated`,
        },
        {
            icon: <IconBolt size={14}/>,
            label: t`Attendee Cancelled`,
            value: 'attendee.cancelled',
            description: t`When an attendee is cancelled`,
        },
        {
            icon: <IconBolt size={14}/>,
            label: t`Check-in Created`,
            value: 'checkin.created',
            description: t`When an attendee is checked in`,
        },
        {
            icon: <IconBolt size={14}/>,
            label: t`Check-in Deleted`,
            value: 'checkin.deleted',
            description: t`When a check-in is deleted`,
        },
    ];

    return (
        <>
            <TextInput
                {...form.getInputProps('url')}
                required
                label={t`Webhook URL`}
                placeholder={t`https://webhook-domain.com/webhook`}
            />

            <CustomSelect
                label={t`Event Types`}
                description={t`Select which events will trigger this webhook`}
                placeholder={t`Select event types`}
                required
                form={form}
                name="event_types"
                optionList={eventTypeOptions}
                multiple
            />

            <CustomSelect
                label={t`Status`}
                required
                form={form}
                name="status"
                optionList={statusOptions}
            />
        </>
    );
}
