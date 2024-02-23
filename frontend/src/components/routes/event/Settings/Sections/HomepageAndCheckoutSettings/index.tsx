import {t} from "@lingui/macro";
import {Button, NumberInput, SimpleGrid, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router-dom";
import {useEffect} from "react";
import {EventSettings} from "../../../../../../types.ts";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.ts";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {Editor} from "../../../../../common/Editor";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";

export const HomepageAndCheckoutSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const form = useForm({
        initialValues: {
            ticket_page_message: '',
            pre_checkout_message: '',
            post_checkout_message: '',
            order_timeout_in_minutes: 15,
        }
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            form.setValues({
                ticket_page_message: eventSettingsQuery.data.ticket_page_message,
                pre_checkout_message: eventSettingsQuery.data.pre_checkout_message,
                post_checkout_message: eventSettingsQuery.data.post_checkout_message,
                order_timeout_in_minutes: eventSettingsQuery.data.order_timeout_in_minutes,
            });
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Homepage Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Checkout Messaging`}
                description={t`Customize the event homepage and checkout messaging`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isLoading}>
                    <Editor
                        label={t`Ticket page message`}
                        value={form.values.ticket_page_message || ''}
                        description={t`This message is how below the `}
                        onChange={(value) => form.setFieldValue('ticket_page_message', value)}
                    />

                    <Editor
                        label={t`Pre Checkout message`}
                        value={form.values.pre_checkout_message || ''}
                        description={t`This message is how below the `}
                        onChange={(value) => form.setFieldValue('pre_checkout_message', value)}
                    />

                    <Editor
                        label={t`Post Checkout message`}
                        value={form.values.post_checkout_message || ''}
                        description={t`This message is how below the `}
                        onChange={(value) => form.setFieldValue('post_checkout_message', value)}
                    />

                    <SimpleGrid cols={2}>
                        <NumberInput
                            label={t`Order timeout`}
                            description={t`How many minutes the customer has to complete their order`}
                            {...form.getInputProps('order_timeout_in_minutes')}
                        />
                        <TextInput
                            label={t`Continue button text`}
                            description={t`The text to display in the 'Continue' button. Defaults to 'Continue'`}
                            {...form.getInputProps('continue_button_text')}
                        />
                    </SimpleGrid>

                    <Button loading={updateMutation.isLoading} type={'submit'}>
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}