import {t} from "@lingui/macro";
import {Button, Switch, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router-dom";
import {useEffect} from "react";
import {EventSettings} from "../../../../../../types.ts";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {Editor} from "../../../../../common/Editor";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";

export const EmailSettings = () => {
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
            form.setValues({
                support_email: eventSettingsQuery.data.support_email,
                email_footer_message: eventSettingsQuery.data.email_footer_message,
            });
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Email Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Email & Notification Settings`}
                description={t`Customize the email and notification settings for this event`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                    <TextInput
                        {...form.getInputProps('support_email')}
                        description={t`Any queries from ticket holders will be sent to this email address. This will also be used as the "reply-to" address for all emails sent from this event`}
                        label={t`Support Email`}
                    />

                    <Editor
                        label={t`Email footer message`}
                        value={form.values.email_footer_message || ''}
                        description={t`This message will be included in the footer of all emails sent from this event`}
                        onChange={(value) => form.setFieldValue('email_footer_message', value)}
                    />

                    <h3>{t`Notification Settings`}</h3>
                    <Switch
                        {...form.getInputProps('notify_organizer_of_new_orders', {type: 'checkbox'})}
                        label={t`Notify organizer of new orders`}
                        description={t`If enabled, the organizer will receive an email notification when a new order is placed`}
                    />

                    <Button loading={updateMutation.isPending} type={'submit'}>
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}
