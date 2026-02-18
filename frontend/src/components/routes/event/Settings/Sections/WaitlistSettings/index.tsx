import {t} from "@lingui/macro";
import {Button, NumberInput, Switch, Text} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";

export const WaitlistSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const form = useForm({
        initialValues: {
            waitlist_auto_process: false,
            waitlist_offer_timeout_minutes: null as number | null,
        }
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            form.setValues({
                waitlist_auto_process: eventSettingsQuery.data.waitlist_auto_process ?? false,
                waitlist_offer_timeout_minutes: eventSettingsQuery.data.waitlist_offer_timeout_minutes ?? null,
            });
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: typeof form.values) => {
        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    };

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Waitlist`}
                description={t`When a product sells out, customers can join a waitlist to be notified when spots become available.

`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                    <Switch
                        {...form.getInputProps('waitlist_auto_process', {type: 'checkbox'})}
                        label={t`Auto-Process Waitlist`}
                        description={t`Automatically offer tickets to the next person when capacity becomes available. If disabled, you can manually process the waitlist from the Waitlist page.`}
                    />

                    <NumberInput
                        {...form.getInputProps('waitlist_offer_timeout_minutes')}
                        label={t`Offer Timeout`}
                        description={t`How long a customer has to complete their purchase after receiving an offer. Leave empty for no timeout.`}
                        placeholder={t`e.g. 180 (3 hours)`}
                        min={1}
                        max={10080}
                        suffix={` ${t`minutes`}`}
                        mt="md"
                    />

                    <Button loading={updateMutation.isPending} type="submit" mt="xl">
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
};
