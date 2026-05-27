import {t} from "@lingui/macro";
import {Button, Select, Switch, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect, useState} from "react";
import {GeoPlace, VenueAddress} from "../../../../../../types.ts";
import {Card} from "../../../../../common/Card";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import countries from "../../../../../../../data/countries.json";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {InputGroup} from "../../../../../common/InputGroup";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {Editor} from "../../../../../common/Editor";
import {isEmptyHtml} from "../../../../../../utilites/helpers.ts";
import {isAddressSet} from "../../../../../../utilites/addressUtilities.ts";
import {AddressAutocomplete} from "../../../../../common/AddressAutocomplete";
import {useCreateLocation} from "../../../../../../mutations/useCreateLocation.ts";
import {useUpdateEventLocation} from "../../../../../../mutations/useUpdateEventLocation.ts";
import {LocationType} from "../../../../../../types.ts";

const emptyAddress = (): VenueAddress => ({
    venue_name: "",
    address_line_1: "",
    address_line_2: "",
    city: "",
    state_or_region: "",
    zip_or_postal_code: "",
    country: "",
});

export const LocationSettings = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateSettingsMutation = useUpdateEventSettings();
    const updateEventLocationMutation = useUpdateEventLocation();
    const createLocationMutation = useCreateLocation();
    const event = eventQuery.data;
    const organizerId = event?.organizer?.id ?? event?.organizer_id;
    const [latLng, setLatLng] = useState<{lat: number | null; lng: number | null; provider: string | null; placeId: string | null}>({
        lat: null,
        lng: null,
        provider: null,
        placeId: null,
    });

    const form = useForm({
        initialValues: {
            location_details: emptyAddress(),
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
            location_details: (value, values) => {
                if (!values.is_online_event && !isAddressSet(value)) {
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
            const source = eventLocation?.location?.structured_address;
            form.setValues({
                location_details: {
                    venue_name: source?.venue_name || "",
                    address_line_1: source?.address_line_1 || "",
                    address_line_2: source?.address_line_2 || "",
                    city: source?.city || "",
                    state_or_region: source?.state_or_region || "",
                    zip_or_postal_code: source?.zip_or_postal_code || "",
                    country: source?.country || "",
                },
                is_online_event: eventLocation?.type === LocationType.Online,
                online_event_connection_details: eventLocation?.online_event_connection_details ?? "",
                maps_url: eventSettingsQuery.data.maps_url || "",
            });
            setLatLng({
                lat: eventLocation?.location?.latitude ?? null,
                lng: eventLocation?.location?.longitude ?? null,
                provider: eventLocation?.location?.provider ?? null,
                placeId: eventLocation?.location?.provider_place_id ?? null,
            });
        }
    }, [eventSettingsQuery.isFetched, eventQuery.isFetched]);

    const handlePlaceSelected = (place: GeoPlace) => {
        form.setValues({
            ...form.values,
            location_details: {
                venue_name: place.address.venue_name || "",
                address_line_1: place.address.address_line_1 || "",
                address_line_2: place.address.address_line_2 || "",
                city: place.address.city || "",
                state_or_region: place.address.state_or_region || "",
                zip_or_postal_code: place.address.zip_or_postal_code || "",
                country: place.address.country || "",
            },
        });
        setLatLng({
            lat: place.latitude ?? null,
            lng: place.longitude ?? null,
            provider: place.provider,
            placeId: place.provider_place_id,
        });
    };

    const handleSubmit = async (values: {location_details: VenueAddress; is_online_event?: boolean; online_event_connection_details?: string | null; maps_url?: string}) => {
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
                if (!organizerId || !isAddressSet(values.location_details)) {
                    showError(t`Enter a venue name or address for in-person events`);
                    return;
                }

                let locationIdForEvent: number | null = event.event_location?.location_id ?? null;
                const existingAddress = event.event_location?.location?.structured_address ?? null;
                const addressUnchanged = existingAddress !== null
                    && (existingAddress.venue_name || "") === (values.location_details.venue_name || "")
                    && (existingAddress.address_line_1 || "") === (values.location_details.address_line_1 || "")
                    && (existingAddress.address_line_2 || "") === (values.location_details.address_line_2 || "")
                    && (existingAddress.city || "") === (values.location_details.city || "")
                    && (existingAddress.state_or_region || "") === (values.location_details.state_or_region || "")
                    && (existingAddress.zip_or_postal_code || "") === (values.location_details.zip_or_postal_code || "")
                    && (existingAddress.country || "") === (values.location_details.country || "");

                if (!addressUnchanged || locationIdForEvent === null) {
                    const created = await createLocationMutation.mutateAsync({
                        organizerId,
                        payload: {
                            name: values.location_details.venue_name || null,
                            structured_address: values.location_details,
                            latitude: latLng.lat,
                            longitude: latLng.lng,
                            provider: latLng.provider,
                            provider_place_id: latLng.placeId,
                        },
                    });
                    locationIdForEvent = (created.data.id as number | undefined) ?? null;
                }

                if (locationIdForEvent === null) {
                    showError(t`Could not save location`);
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
            showError(t`Could not save location`);
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

                    {!form.values.is_online_event && (
                        <>
                            {organizerId && (
                                <AddressAutocomplete
                                    organizerId={organizerId}
                                    onPlaceSelected={handlePlaceSelected}
                                    country={form.values.location_details.country || undefined}
                                />
                            )}

                            <TextInput
                                {...form.getInputProps("location_details.venue_name")}
                                label={t`Venue Name`}
                                placeholder={t`Conference Center`}
                            />
                            <InputGroup>
                                <TextInput
                                    {...form.getInputProps("location_details.address_line_1")}
                                    label={t`Address Line 1`}
                                    placeholder={t`123 Main Street`}
                                />
                                <TextInput
                                    {...form.getInputProps("location_details.address_line_2")}
                                    label={t`Address Line 2`}
                                    placeholder={t`Suite 100`}
                                />
                            </InputGroup>
                            <InputGroup>
                                <TextInput
                                    {...form.getInputProps("location_details.city")}
                                    label={t`City`}
                                    placeholder={t`San Francisco`}
                                />
                                <TextInput
                                    {...form.getInputProps("location_details.state_or_region")}
                                    label={t`State or Region`}
                                    placeholder={t`California`}
                                />
                            </InputGroup>
                            <InputGroup>
                                <TextInput
                                    {...form.getInputProps("location_details.zip_or_postal_code")}
                                    label={t`Zip or Postal Code`}
                                    placeholder={t`94103`}
                                />
                                <Select
                                    searchable
                                    data={countries}
                                    {...form.getInputProps("location_details.country")}
                                    label={t`Country`}
                                    placeholder={t`United States`}
                                />
                            </InputGroup>
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
