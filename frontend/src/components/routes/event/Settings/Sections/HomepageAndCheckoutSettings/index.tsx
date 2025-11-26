import {t} from "@lingui/macro";
import {Button, NumberInput, Switch} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {EventSettings} from "../../../../../../types.ts";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {Editor} from "../../../../../common/Editor";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {isEmptyHtml} from "../../../../../../utilites/helpers.ts";

export const HomepageAndCheckoutSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const form = useForm({
        initialValues: {
            pre_checkout_message: '',
            post_checkout_message: '',
            order_timeout_in_minutes: 15,
            require_auth_for_checkout: false,
        },
        transformValues: (values) => ({
            ...values,
            pre_checkout_message: isEmptyHtml(values.pre_checkout_message) ? null : values.pre_checkout_message,
            post_checkout_message: isEmptyHtml(values.post_checkout_message) ? null : values.post_checkout_message,
        }),
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            form.setValues({
                pre_checkout_message: eventSettingsQuery.data.pre_checkout_message,
                post_checkout_message: eventSettingsQuery.data.post_checkout_message,
                order_timeout_in_minutes: eventSettingsQuery.data.order_timeout_in_minutes,
                require_auth_for_checkout: !!eventSettingsQuery.data.require_auth_for_checkout,
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
                heading={t`Checkout Settings`}
                description={t`Customize the event homepage and checkout messaging`}
            />
            <form onSubmit={form.onSubmit(handleSubmit as any)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                    <Editor
                        label={t`Pre Checkout message`}
                        value={form.values.pre_checkout_message || ''}
                        description={t`Shown to the customer before they checkout`}
                        onChange={(value) => form.setFieldValue('pre_checkout_message', value)}
                    />

                    <Editor
                        label={t`Post Checkout message`}
                        value={form.values.post_checkout_message || ''}
                        description={(
                            <>
                                <p>
                                    {t`Shown to the customer after they checkout, on the order summary page.`}
                                </p>
                                <p>
                                    {t`This message will only be shown if order is completed successfully. Orders awaiting payment will not show this message`}
                                </p>
                            </>
                        )}
                        onChange={(value) => form.setFieldValue('post_checkout_message', value)}
                    />

                    <NumberInput
                        label={t`Order timeout`}
                        description={t`How many minutes the customer has to complete their order. We recommend at least 15 minutes`}
                        {...form.getInputProps('order_timeout_in_minutes')}
                    />

                    <Switch
                        mt="md"
                        label={t`Require login to buy tickets`}
                        description={t`Customers will be redirected to Authentik before starting checkout when enabled.`}
                        checked={form.values.require_auth_for_checkout}
                        onChange={(event) => form.setFieldValue('require_auth_for_checkout', event.currentTarget.checked)}
                    />

                    <Button loading={updateMutation.isPending} type={'submit'}>
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}
