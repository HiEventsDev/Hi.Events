import {t} from "@lingui/macro";
import {Button, Switch, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {Card} from "../../../../../common/Card";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {Editor} from "../../../../../common/Editor";
import {isEmptyHtml} from "../../../../../../utilites/helpers.ts";
import {isAddressSet, sameAddress} from "../../../../../../utilites/addressUtilities.ts";
import {LocationPicker, LocationPickerValue} from "../../../../../common/LocationPicker";
import {useCreateLocation} from "../../../../../../mutations/useCreateLocation.ts";
import {useUpdateEventLocation} from "../../../../../../mutations/useUpdateEventLocation.ts";
import {LocationType} from "../../../../../../types.ts";

export const LocationSettings = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateSettingsMutation = useUpdateEventSettings();
    const updateEventLocationMutation = useUpdateEventLocation();
    const createLocationMutation = useCreateLocation();
    const event = eventQuery.data;
    const organizerId = event?.organizer?.id ?? event?.organizer_id;

    const form = useForm({
        initialValues: {
            location: null as LocationPickerValue | null,
            is_online_event: false,
            online_event_connection_details: "",
            maps_url: "",
        },
        validate: {
            online_event_connection_details: (value, values) => {
                if (values.is_online_event && (!value || isEmptyHtml(value))) {
                    return t`Connection details are required for online events`;
                }
                return null;
            },
            location: (value, values) => {
                if (values.is_online_event) {
                    return null;
                }
                if (!value || (value.kind === 'new' && !isAddressSet(value.address))) {
                    return t`Enter a venue name or address for in-person events`;
                }
                return null;
            },
        },
        transformValues: (values) => ({
            ...values,
            online_event_connection_details: isEmptyHtml(values.online_event_connection_details)
                ? null
                : values.online_event_connection_details,
        }),
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery.data && eventQuery.isFetched && eventQuery.data) {
            const eventLocation = event?.event_location;
            form.setValues({
                location: eventLocation?.location_id && eventLocation.location
                    ? {kind: 'saved', location: eventLocation.location}
                    : null,
                is_online_event: eventLocation?.type === LocationType.Online,
                online_event_connection_details: eventLocation?.online_event_connection_details ?? "",
                maps_url: eventSettingsQuery.data.maps_url || "",
            });
        }
    }, [eventSettingsQuery.isFetched, eventQuery.isFetched]);

    const resolveLocationId = async (value: LocationPickerValue): Promise<number | null> => {
        if (value.kind === 'saved') {
            return value.location.id ? Number(value.location.id) : null;
        }

        if (!organizerId || !isAddressSet(value.address)) {
            return null;
        }

        const existingLocationId = event?.event_location?.location_id ?? null;
        const existingAddress = event?.event_location?.location?.structured_address ?? null;
        if (existingLocationId !== null && existingAddress !== null && sameAddress(existingAddress, value.address)) {
            return existingLocationId;
        }

        const created = await createLocationMutation.mutateAsync({
            organizerId,
            payload: {
                name: value.address.venue_name || null,
                structured_address: value.address,
                latitude: value.latitude,
                longitude: value.longitude,
                provider: value.provider,
                provider_place_id: value.providerPlaceId,
            },
        });

        return (created.data.id as number | undefined) ?? null;
    };

    const handleSubmit = async (values: {location: LocationPickerValue | null; is_online_event?: boolean; online_event_connection_details?: string | null; maps_url?: string}) => {
        if (!eventId || !event) return;
        try {
            if (values.is_online_event) {
                await updateEventLocationMutation.mutateAsync({
                    eventId,
                    eventLocation: {
                        type: LocationType.Online,
                        online_event_connection_details: values.online_event_connection_details ?? null,
                    },
                });
            } else {
                const locationIdForEvent = values.location ? await resolveLocationId(values.location) : null;

                if (locationIdForEvent === null) {
                    showError(t`Enter a venue name or address for in-person events`);
                    return;
                }

                await updateEventLocationMutation.mutateAsync({
                    eventId,
                    eventLocation: {
                        type: LocationType.InPerson,
                        location_id: locationIdForEvent,
                    },
                });
            }

            await updateSettingsMutation.mutateAsync({
                eventSettings: {maps_url: values.maps_url},
                eventId,
            });

            showSuccess(t`Successfully Updated Location`);
        } catch (error) {
            formErrorHandle(form, error);
        }
    };

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Location`}
                description={t`Event location & venue details`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateSettingsMutation.isPending || createLocationMutation.isPending || updateEventLocationMutation.isPending}>
                    <Switch
                        {...form.getInputProps("is_online_event", {type: "checkbox"})}
                        label={t`This is an online event`}
                    />

                    {form.values.is_online_event && (
                        <Editor
                            value={form.values.online_event_connection_details || ""}
                            error={form.errors.online_event_connection_details as string}
                            label={t`Connection Details`}
                            description={(
                                <>
                                    <p>{t`Include connection details for your online event. These details will be shown on the order summary page and attendee ticket page.`}</p>
                                    <p>{t`These details will only be shown if the order is completed successfully.`}</p>
                                </>
                            )}
                            onChange={(value) => form.setFieldValue("online_event_connection_details", value)}
                        />
                    )}

                    {(!form.values.is_online_event && organizerId) && (
                        <>
                            <LocationPicker
                                organizerId={organizerId}
                                value={form.values.location}
                                onChange={(value) => form.setFieldValue('location', value)}
                                error={form.errors.location}
                            />
                            <TextInput
                                {...form.getInputProps("maps_url")}
                                description={t`If blank, the address will be used to generate a Google Maps link`}
                                label={t`Custom Maps URL`}
                                placeholder={t`https://example-maps-service.com/...`}
                            />
                        </>
                    )}

                    <Button loading={updateSettingsMutation.isPending || createLocationMutation.isPending || updateEventLocationMutation.isPending} type={"submit"}>
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
};
