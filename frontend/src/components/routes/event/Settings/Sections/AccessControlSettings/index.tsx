import {t} from "@lingui/macro";
import {Button, PasswordInput, Switch} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";

export const AccessControlSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const form = useForm({
        initialValues: {
            event_password: '' as string,
            require_order_approval: false,
        }
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            form.setValues({
                event_password: eventSettingsQuery.data.event_password ?? '',
                require_order_approval: eventSettingsQuery.data.require_order_approval || false,
            });
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: typeof form.values) => {
        updateMutation.mutate({
            eventSettings: {
                event_password: values.event_password || null,
                require_order_approval: values.require_order_approval,
            },
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
                heading={t`Access Control`}
                description={t`Restrict access to your event page and control order approvals.`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                    <PasswordInput
                        {...form.getInputProps('event_password')}
                        label={t`Event Password`}
                        description={t`When set, visitors must enter this password before they can view the event page and purchase tickets.`}
                        placeholder={t`Leave empty for public access`}
                        mb="md"
                    />

                    <Switch
                        {...form.getInputProps('require_order_approval', {type: 'checkbox'})}
                        label={t`Require Order Approval`}
                        description={t`When enabled, free orders will be held in an "Awaiting Approval" status until you manually approve or reject them.`}
                    />

                    <Button loading={updateMutation.isPending} type="submit" mt="xl">
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
};
