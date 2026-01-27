import {t} from "@lingui/macro";
import {Button, Switch, TextInput, Textarea} from "@mantine/core";
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
import {IconExternalLink, IconUser} from "@tabler/icons-react";

export const RegistrationSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const form = useForm({
        initialValues: {
            is_external_registration: false,
            external_registration_url: '',
            external_registration_button_text: '',
            external_registration_message: '',
            external_registration_host: '',
        },
        validate: {
            external_registration_url: (value, values) => {
                if (values.is_external_registration && !value) {
                    return t`External registration URL is required`;
                }
                if (value && !value.match(/^https?:\/\/.+/)) {
                    return t`Please enter a valid URL`;
                }
            },
            external_registration_button_text: (value, values) => {
                if (values.is_external_registration && !value) {
                    return t`Button text is required`;
                }
            },
        },
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            form.setValues({
                is_external_registration: eventSettingsQuery.data.is_external_registration ?? false,
                external_registration_url: eventSettingsQuery.data.external_registration_url ?? '',
                external_registration_button_text: eventSettingsQuery.data.external_registration_button_text ?? '',
                external_registration_message: eventSettingsQuery.data.external_registration_message ?? '',
                external_registration_host: eventSettingsQuery.data.external_registration_host ?? '',
            });
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Registration settings updated successfully`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Registration Settings`}
                description={t`Configure how attendees register for your event`}
            />
            <form onSubmit={form.onSubmit(handleSubmit as any)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                    <Switch
                        {...form.getInputProps('is_external_registration', {type: 'checkbox'})}
                        label={t`Use external registration`}
                        description={t`Enable this if you're using an external platform like Luma, Eventbrite, or your own registration system`}
                        mb="md"
                    />

                    {form.values.is_external_registration && (
                        <>
                            <TextInput
                                {...form.getInputProps('external_registration_host')}
                                label={t`Event Host / Organizer`}
                                placeholder={t`Luma`}
                                description={t`The name of the external platform or organizer hosting the registration`}
                                leftSection={<IconUser size={18}/>}
                                maxLength={255}
                                mb="md"
                            />

                            <TextInput
                                {...form.getInputProps('external_registration_url')}
                                label={t`External Registration URL`}
                                placeholder="https://lu.ma/your-event"
                                description={t`The URL where attendees will be redirected to register`}
                                required
                                leftSection={<IconExternalLink size={18}/>}
                                mb="md"
                            />

                            <TextInput
                                {...form.getInputProps('external_registration_button_text')}
                                label={t`Button Text`}
                                placeholder={t`Register on Luma`}
                                description={t`The text shown on the registration button`}
                                required
                                maxLength={100}
                                mb="md"
                            />

                            <Textarea
                                {...form.getInputProps('external_registration_message')}
                                label={t`Registration Message`}
                                placeholder={t`This event uses external registration.`}
                                description={t`The message shown on the event page explaining external registration`}
                                maxLength={500}
                                rows={3}
                                mb="md"
                            />

                            <div style={{
                                background: 'var(--mantine-color-yellow-0)',
                                border: '1px solid var(--mantine-color-yellow-3)',
                                borderRadius: '8px',
                                padding: '12px 16px',
                                marginBottom: '16px',
                            }}>
                                <p style={{margin: 0, fontSize: '0.875rem', color: 'var(--mantine-color-yellow-9)'}}>
                                    <strong>{t`Note:`}</strong> {t`When external registration is enabled, the ticket selection and checkout will be hidden. Attendees will be redirected to your external registration URL.`}
                                </p>
                            </div>
                        </>
                    )}

                    <Button loading={updateMutation.isPending} type={'submit'}>
                        {t`Save Changes`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}
