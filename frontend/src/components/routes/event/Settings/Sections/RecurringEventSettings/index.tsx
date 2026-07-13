import {t} from "@lingui/macro";
import {Button, Switch} from "@mantine/core";
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

export const RecurringEventSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const form = useForm({
        initialValues: {
            show_available_occurrence_capacity: false,
            hide_sold_out_occurrences: false,
        },
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            form.setValues({
                show_available_occurrence_capacity: eventSettingsQuery.data.show_available_occurrence_capacity ?? false,
                hide_sold_out_occurrences: eventSettingsQuery.data.hide_sold_out_occurrences ?? false,
            });
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Recurring Event Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Recurring Event Settings`}
                description={t`Control how dates and times are shown on the event page`}
            />
            <form onSubmit={form.onSubmit(handleSubmit as any)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                    <Switch
                        mt="md"
                        label={t`Show remaining capacity on event dates`}
                        description={t`Display how many spots are left on each date in the ticket widget. You can override this for individual dates.`}
                        {...form.getInputProps('show_available_occurrence_capacity', {type: 'checkbox'})}
                    />

                    <Switch
                        mt="md"
                        label={t`Hide sold out dates and times`}
                        description={t`Remove sold out dates and times from the event page entirely. When disabled, they remain visible and are labelled as sold out.`}
                        {...form.getInputProps('hide_sold_out_occurrences', {type: 'checkbox'})}
                    />

                    <Button loading={updateMutation.isPending} type={'submit'}>
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}
