import {t} from "@lingui/macro";
import {NumberInput, Switch} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {EventType} from "../../../../../../types.ts";
import {SettingsPage, SettingsRow, SettingsSaveFooter, SettingsSection} from "../../../../../common/SettingsPage";

export const WaitlistSettings = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const isRecurring = event?.type === EventType.RECURRING;
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
            const values = {
                waitlist_auto_process: eventSettingsQuery.data.waitlist_auto_process ?? false,
                waitlist_offer_timeout_minutes: eventSettingsQuery.data.waitlist_offer_timeout_minutes ?? null,
            };
            form.setInitialValues(values as typeof form.values);
            form.setValues(values);
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: typeof form.values) => {
        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                form.resetDirty();
                showSuccess(t`Successfully Updated Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    };

    return (
        <SettingsPage
            title={t`Waitlist`}
            description={isRecurring
                ? t`When a product sells out, customers can join a waitlist to be notified when spots become available. Customers join the waitlist for a specific date, and offers are made per date.`
                : t`When a product sells out, customers can join a waitlist to be notified when spots become available.`}
        >
            <SettingsSection>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                        <SettingsRow
                            label={t`Auto-Process Waitlist`}
                            description={t`Automatically offer tickets to the next person when capacity becomes available. If disabled, you can manually process the waitlist from the Waitlist page.`}
                            htmlFor="event-waitlist-auto-process"
                            inlineControl
                        >
                            <Switch
                                id="event-waitlist-auto-process"
                                {...form.getInputProps('waitlist_auto_process', {type: 'checkbox'})}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`Offer Timeout`}
                            description={t`How long a customer has to complete their purchase after receiving an offer. Leave empty for no timeout.`}
                            htmlFor="event-waitlist-offer-timeout"
                        >
                            <NumberInput
                                id="event-waitlist-offer-timeout"
                                {...form.getInputProps('waitlist_offer_timeout_minutes')}
                                placeholder={t`e.g. 180 (3 hours)`}
                                min={1}
                                max={10080}
                                suffix={` ${t`minutes`}`}
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
};
