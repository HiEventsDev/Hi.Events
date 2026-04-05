import {t} from "@lingui/macro";
import {Button, Switch, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {EventSettings} from "../../../../../../types.ts";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {CustomSelect, ItemProps} from "../../../../../common/CustomSelect";
import {IconCoin, IconCoins, IconReceiptTax} from "@tabler/icons-react";
import {SelfServiceSettings} from "../../../../../common/SelfServiceSettings";

export const MiscSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const form = useForm({
        initialValues: {
            price_display_mode: 'EXCLUSIVE',
            hide_getting_started_page: false,
            hide_start_date: false,
            allow_attendee_self_edit: false,
            external_ticket_url: '',
            checkout_validation_webhook_url: '',
        }
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            form.setValues({
                price_display_mode: eventSettingsQuery.data.price_display_mode,
                hide_getting_started_page: eventSettingsQuery.data.hide_getting_started_page,
                hide_start_date: eventSettingsQuery.data.hide_start_date ?? false,
                allow_attendee_self_edit: eventSettingsQuery.data.allow_attendee_self_edit ?? false,
                external_ticket_url: eventSettingsQuery.data.external_ticket_url || '',
                checkout_validation_webhook_url: eventSettingsQuery.data.checkout_validation_webhook_url || '',
            });
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Misc Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    const priceOptions: ItemProps[] = [
        {
            icon: <IconCoins/>,
            label: t`Include tax and fees in the price`,
            value: 'INCLUSIVE',
            description: t`The price displayed to the customer will include taxes and fees.`,
        },
        {
            icon: <IconCoin/>,
            label: t`Show tax and fees separately`,
            value: 'EXCLUSIVE',
            description: t`The price displayed to the customer will not include taxes and fees. They will be shown separately`,
        },
        {
            icon: <IconReceiptTax/>,
            label: t`Include tax in the price, but not fees`,
            value: 'TAX_INCLUSIVE',
            description: t`The price displayed to the customer will include taxes but fees will be shown separately`,
        },
    ];

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Miscellaneous Settings`}
                description={t`Customize the miscellaneous settings for this event`}
            />
            <form onSubmit={form.onSubmit(handleSubmit as any)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                    <CustomSelect
                        optionList={priceOptions}
                        form={form}
                        name={'price_display_mode'}
                        label={t`Price display mode`}
                        required
                    />
                    {form.errors['price_display_mode'] && (
                        <div style={{color: 'red'}}>
                            {form.errors['price_display_mode']}
                        </div>
                    )}

                    <Switch
                        {...form.getInputProps('hide_getting_started_page', {type: 'checkbox'})}
                        label={t`Hide getting started page`}
                        description={t`Hide the getting started page from the sidebar`}
                    />

                    <Switch
                        {...form.getInputProps('hide_start_date', {type: 'checkbox'})}
                        label={t`Hide event start date`}
                        description={t`Hide the start date from the public event page. Useful when the date is not yet confirmed.`}
                    />

                    <SelfServiceSettings
                        value={form.values.allow_attendee_self_edit}
                        onChange={(value) => form.setFieldValue('allow_attendee_self_edit', value)}
                    />

                    <TextInput
                        {...form.getInputProps('external_ticket_url')}
                        label={t`External Ticket URL`}
                        description={t`If set, visitors will be redirected to this URL instead of seeing the built-in ticket selection. Useful for linking to an external ticketing platform.`}
                        placeholder="https://example.com/tickets"
                    />

                    <TextInput
                        {...form.getInputProps('checkout_validation_webhook_url')}
                        label={t`Checkout Validation Webhook URL`}
                        description={t`If set, a synchronous POST request will be sent to this URL before payment is processed. The webhook can approve or reject the order. Return HTTP 200 to approve, or HTTP 4xx with a JSON "message" field to reject.`}
                        placeholder="https://example.com/api/validate-checkout"
                    />

                    <Button loading={updateMutation.isPending} type={'submit'}>
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}
