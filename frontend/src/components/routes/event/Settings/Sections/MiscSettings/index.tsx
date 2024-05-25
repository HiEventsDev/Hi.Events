import {t} from "@lingui/macro";
import {Button, Switch} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router-dom";
import {useEffect} from "react";
import {EventSettings} from "../../../../../../types.ts";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {CustomSelect, ItemProps} from "../../../../../common/CustomSelect";
import {IconCoin, IconCoins} from "@tabler/icons-react";

export const MiscSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const form = useForm({
        initialValues: {
            price_display_mode: 'EXCLUSIVE',
            hide_getting_started_page: false,
        }
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            form.setValues({
                price_display_mode: eventSettingsQuery.data.price_display_mode,
                hide_getting_started_page: eventSettingsQuery.data.hide_getting_started_page,
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
    ];

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Miscellaneous Settings`}
                description={t`Customize the miscellaneous settings for this event`}
            />
            <form onSubmit={form.onSubmit(handleSubmit as any)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isLoading}>
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

                    <Button loading={updateMutation.isLoading} type={'submit'}>
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}