import {t} from "@lingui/macro";
import {Switch} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {EventSettings} from "../../../../../../types.ts";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {SettingsPage, SettingsRow, SettingsSaveFooter, SettingsSection} from "../../../../../common/SettingsPage";

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
            const values = {
                show_available_occurrence_capacity: eventSettingsQuery.data.show_available_occurrence_capacity ?? false,
                hide_sold_out_occurrences: eventSettingsQuery.data.hide_sold_out_occurrences ?? false,
            };
            form.setInitialValues(values as typeof form.values);
            form.setValues(values);
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                form.resetDirty();
                showSuccess(t`Successfully Updated Recurring Event Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <SettingsPage
            title={t`Recurring Event`}
            description={t`Control how dates and times are shown on the event page`}
        >
            <SettingsSection>
                <form onSubmit={form.onSubmit(handleSubmit as any)}>
                    <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                        <SettingsRow
                            label={t`Show remaining capacity on event dates`}
                            description={t`Display how many spots are left on each date in the ticket widget. You can override this for individual dates.`}
                            htmlFor="event-recurring-show-capacity"
                            inlineControl
                        >
                            <Switch
                                id="event-recurring-show-capacity"
                                {...form.getInputProps('show_available_occurrence_capacity', {type: 'checkbox'})}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`Hide sold out dates and times`}
                            description={t`Remove sold out dates and times from the event page entirely. When disabled, they remain visible and are labelled as sold out.`}
                            htmlFor="event-recurring-hide-sold-out"
                            inlineControl
                        >
                            <Switch
                                id="event-recurring-hide-sold-out"
                                {...form.getInputProps('hide_sold_out_occurrences', {type: 'checkbox'})}
                            />
                        </SettingsRow>
                    </fieldset>
                    <SettingsSaveFooter
                        isDirty={form.isDirty()}
                        isSaving={updateMutation.isPending}
                        onDiscard={form.reset}
                    />
                </form>
            </SettingsSection>
        </SettingsPage>
    );
}
