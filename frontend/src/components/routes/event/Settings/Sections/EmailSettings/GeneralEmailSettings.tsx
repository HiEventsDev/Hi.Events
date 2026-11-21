import {t} from "@lingui/macro";
import {Switch, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {EventSettings} from "../../../../../../types.ts";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {Editor} from "../../../../../common/Editor";
import {SettingsBlock, SettingsRow, SettingsSaveFooter, SettingsSection} from "../../../../../common/SettingsPage";

export const GeneralEmailSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const form = useForm({
        initialValues: {
            support_email: '',
            email_footer_message: '',
            notify_organizer_of_new_orders: true,
        }
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            const values = {
                support_email: eventSettingsQuery.data.support_email ?? '',
                email_footer_message: eventSettingsQuery.data.email_footer_message,
                notify_organizer_of_new_orders: eventSettingsQuery.data.notify_organizer_of_new_orders,
            };
            form.setInitialValues(values);
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
                showSuccess(t`Successfully Updated Email Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <SettingsSection>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                    <SettingsRow
                        label={t`Support Email`}
                        description={t`Any queries from product holders will be sent to this email address. This will also be used as the "reply-to" address for all emails sent from this event`}
                        htmlFor="event-email-support-email"
                    >
                        <TextInput
                            id="event-email-support-email"
                            {...form.getInputProps('support_email')}
                        />
                    </SettingsRow>

                    <SettingsBlock>
                        <Editor
                            label={t`Email footer message`}
                            value={form.values.email_footer_message || ''}
                            description={t`This message will be included in the footer of all emails sent from this event`}
                            onChange={(value) => form.setFieldValue('email_footer_message', value)}
                        />
                    </SettingsBlock>

                    <SettingsRow
                        label={t`Notify organizer of new orders`}
                        description={t`If enabled, the organizer will receive an email notification when a new order is placed`}
                        htmlFor="event-email-notify-organizer"
                        inlineControl
                    >
                        <Switch
                            id="event-email-notify-organizer"
                            {...form.getInputProps('notify_organizer_of_new_orders', {type: 'checkbox'})}
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
    );
}
