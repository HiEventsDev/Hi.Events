import {t} from "@lingui/macro";
import {Button, Select, SegmentedControl, Switch, TextInput, NumberInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {Event} from "../../../../../../types.ts";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import countries from "../../../../../../../data/countries.json";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {InputGroup} from "../../../../../common/InputGroup";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {Editor} from "../../../../../common/Editor";
import {isEmptyHtml} from "../../../../../../utilites/helpers.ts";

export const LocationSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const form = useForm({
        initialValues: {
            location_details: {
                venue_name: '',
                address_line_1: '',
                address_line_2: '',
                city: '',
                state_or_region: '',
                zip_or_postal_code: '',
                country: '',
            },
            event_location_type: 'venue' as 'venue' | 'online' | 'hybrid',
            online_event_connection_details: '',
            maps_url: '',
            venue_latitude: null as number | null,
            venue_longitude: null as number | null,
            show_map_on_event_page: false,
        },
        transformValues: (values) => ({
            ...values,
            online_event_connection_details: isEmptyHtml(values.online_event_connection_details) ? null : values.online_event_connection_details,
            is_online_event: values.event_location_type === 'online',
        }),
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery.data) {
            const locationType = eventSettingsQuery.data.event_location_type
                || (eventSettingsQuery.data.is_online_event ? 'online' : 'venue');
            form.setValues({
                location_details: {
                    venue_name: eventSettingsQuery.data.location_details?.venue_name || '',
                    address_line_1: eventSettingsQuery.data.location_details?.address_line_1 || '',
                    address_line_2: eventSettingsQuery.data.location_details?.address_line_2 || '',
                    city: eventSettingsQuery.data.location_details?.city || '',
                    state_or_region: eventSettingsQuery.data.location_details?.state_or_region || '',
                    zip_or_postal_code: eventSettingsQuery.data.location_details?.zip_or_postal_code || '',
                    country: eventSettingsQuery.data.location_details?.country || '',
                },
                event_location_type: locationType,
                online_event_connection_details: eventSettingsQuery.data.online_event_connection_details,
                maps_url: eventSettingsQuery.data.maps_url || '',
                venue_latitude: eventSettingsQuery.data.venue_latitude ?? null,
                venue_longitude: eventSettingsQuery.data.venue_longitude ?? null,
                show_map_on_event_page: eventSettingsQuery.data.show_map_on_event_page ?? false,
            });
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<Event>) => {
        if (form.values.event_location_type === 'online') {
            values.location_details = undefined;
        }

        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Location`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Location`}
                description={t`Event location & venue details`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                    <SegmentedControl
                        {...form.getInputProps('event_location_type')}
                        data={[
                            {label: t`Venue`, value: 'venue'},
                            {label: t`Online`, value: 'online'},
                            {label: t`Hybrid`, value: 'hybrid'},
                        ]}
                        mb="md"
                    />

                    {(form.values.event_location_type === 'online' || form.values.event_location_type === 'hybrid') && (
                        <Editor
                            value={form.values.online_event_connection_details || ''}
                            error={form.errors.online_event_connection_details as string}
                            label={t`Connection Details`}
                            description={(
                                <>
                                    <p>
                                        {t`Include connection details for your online event. These details will be shown on the order summary page and attendee ticket page.`}
                                    </p>
                                    <p>
                                        {t`These details will only be shown if order is completed successfully. Orders awaiting payment will not show this message.`}
                                    </p>
                                </>
                            )}
                            onChange={(value) => form.setFieldValue('online_event_connection_details', value)}
                        />
                    )}
                    {(form.values.event_location_type === 'venue' || form.values.event_location_type === 'hybrid') && (
                        <>
                            <TextInput
                                {...form.getInputProps('location_details.venue_name')}
                                label={t`Venue Name`}
                                placeholder={t`Conference Center`}
                            />
                            <InputGroup>
                                <TextInput
                                    {...form.getInputProps('location_details.address_line_1')}
                                    label={t`Address Line 1`}
                                    placeholder={t`123 Main Street`}
                                />
                                <TextInput
                                    {...form.getInputProps('location_details.address_line_2')}
                                    label={t`Address Line 2`}
                                    placeholder={t`Suite 100`}
                                />
                            </InputGroup>
                            <InputGroup>
                                <TextInput
                                    {...form.getInputProps('location_details.city')}
                                    label={t`City`}
                                    placeholder={t`San Francisco`}
                                />
                                <TextInput
                                    {...form.getInputProps('location_details.state_or_region')}
                                    label={t`State or Region`}
                                    placeholder={t`California`}
                                />
                            </InputGroup>
                            <InputGroup>
                                <TextInput
                                    {...form.getInputProps('location_details.zip_or_postal_code')}
                                    label={t`Zip or Postal Code`}
                                    placeholder={t`94103`}
                                />
                                <Select searchable
                                        data={countries}
                                        {...form.getInputProps('location_details.country')}
                                        label={t`Country`}
                                        placeholder={t`United States`}
                                />
                            </InputGroup>
                            <TextInput
                                {...form.getInputProps('maps_url')}
                                description={t`If blank, the address will be used to generate a Google Maps link`}
                                label={t`Custom Maps URL`}
                                placeholder={t`https://example-maps-service.com/...`}
                            />
                            <InputGroup>
                                <NumberInput
                                    {...form.getInputProps('venue_latitude')}
                                    label={t`Venue Latitude`}
                                    placeholder="37.7749"
                                    decimalScale={7}
                                    min={-90}
                                    max={90}
                                />
                                <NumberInput
                                    {...form.getInputProps('venue_longitude')}
                                    label={t`Venue Longitude`}
                                    placeholder="-122.4194"
                                    decimalScale={7}
                                    min={-180}
                                    max={180}
                                />
                            </InputGroup>
                            <Switch
                                {...form.getInputProps('show_map_on_event_page', {type: 'checkbox'})}
                                label={t`Show embedded Google Map on event page`}
                                description={t`When enabled with coordinates, an interactive map will be displayed on your event page`}
                                mt="sm"
                            />
                        </>
                    )}

                    <Button loading={updateMutation.isPending} type={'submit'}>
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}
