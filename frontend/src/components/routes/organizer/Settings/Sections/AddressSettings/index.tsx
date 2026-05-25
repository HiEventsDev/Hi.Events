import {t} from "@lingui/macro";
import {Button, Select, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect, useState} from "react";
import {Card} from "../../../../../common/Card";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import countries from "../../../../../../../data/countries.json";
import {useGetOrganizer} from "../../../../../../queries/useGetOrganizer.ts";
import {InputGroup} from "../../../../../common/InputGroup";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {AddressAutocomplete} from "../../../../../common/AddressAutocomplete";
import {useCreateLocation} from "../../../../../../mutations/useCreateLocation.ts";
import {useUpdateOrganizerLocation} from "../../../../../../mutations/useUpdateOrganizerLocation.ts";
import {GeoPlace, IdParam, VenueAddress} from "../../../../../../types.ts";

export const AddressSettings = () => {
    const {organizerId} = useParams();
    const organizerQuery = useGetOrganizer(organizerId);
    const createLocationMutation = useCreateLocation();
    const updateOrganizerLocationMutation = useUpdateOrganizerLocation();
    const [latLng, setLatLng] = useState<{lat: number | null; lng: number | null; provider: string | null; placeId: string | null}>({
        lat: null,
        lng: null,
        provider: null,
        placeId: null,
    });

    const form = useForm({
        initialValues: {
            structured_address: {
                venue_name: '',
                address_line_1: '',
                address_line_2: '',
                city: '',
                state_or_region: '',
                zip_or_postal_code: '',
                country: '',
            } as VenueAddress,
        },
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (organizerQuery?.isFetched && organizerQuery.data) {
            const source = organizerQuery.data.location?.structured_address;
            form.setValues({
                structured_address: {
                    venue_name: source?.venue_name || '',
                    address_line_1: source?.address_line_1 || '',
                    address_line_2: source?.address_line_2 || '',
                    city: source?.city || '',
                    state_or_region: source?.state_or_region || '',
                    zip_or_postal_code: source?.zip_or_postal_code || '',
                    country: source?.country || '',
                },
            });
            setLatLng({
                lat: organizerQuery.data.location?.latitude ?? null,
                lng: organizerQuery.data.location?.longitude ?? null,
                provider: organizerQuery.data.location?.provider ?? null,
                placeId: organizerQuery.data.location?.provider_place_id ?? null,
            });
        }
    }, [organizerQuery.isFetched]);

    const handlePlaceSelected = (place: GeoPlace) => {
        form.setValues({
            ...form.values,
            structured_address: {
                venue_name: place.address.venue_name || '',
                address_line_1: place.address.address_line_1 || '',
                address_line_2: place.address.address_line_2 || '',
                city: place.address.city || '',
                state_or_region: place.address.state_or_region || '',
                zip_or_postal_code: place.address.zip_or_postal_code || '',
                country: place.address.country || '',
            },
        });
        setLatLng({
            lat: place.latitude ?? null,
            lng: place.longitude ?? null,
            provider: place.provider,
            placeId: place.provider_place_id,
        });
    };

    const handleSubmit = async (values: {structured_address: VenueAddress}) => {
        if (!organizerId) {
            return;
        }
        const address = values.structured_address;
        const hasAnything = Boolean(
            address.venue_name || address.address_line_1 || address.city
            || address.state_or_region || address.zip_or_postal_code || address.country,
        );
        const existingLocationId = organizerQuery.data?.location_id ?? null;

        try {
            if (!hasAnything && existingLocationId === null) {
                showSuccess(t`Successfully Updated Address`);
                return;
            }

            const existingAddress = organizerQuery.data?.location?.structured_address ?? null;
            const addressUnchanged = hasAnything && existingAddress !== null
                && (existingAddress.venue_name || "") === (address.venue_name || "")
                && (existingAddress.address_line_1 || "") === (address.address_line_1 || "")
                && (existingAddress.address_line_2 || "") === (address.address_line_2 || "")
                && (existingAddress.city || "") === (address.city || "")
                && (existingAddress.state_or_region || "") === (address.state_or_region || "")
                && (existingAddress.zip_or_postal_code || "") === (address.zip_or_postal_code || "")
                && (existingAddress.country || "") === (address.country || "");

            if (addressUnchanged) {
                showSuccess(t`Successfully Updated Address`);
                return;
            }

            let locationId: IdParam | null = null;
            if (hasAnything) {
                const created = await createLocationMutation.mutateAsync({
                    organizerId,
                    payload: {
                        name: address.venue_name || null,
                        structured_address: address,
                        latitude: latLng.lat,
                        longitude: latLng.lng,
                        provider: latLng.provider,
                        provider_place_id: latLng.placeId,
                    },
                });
                locationId = created.data.id ?? null;
            }

            await updateOrganizerLocationMutation.mutateAsync({
                organizerId,
                locationId,
            });

            showSuccess(t`Successfully Updated Address`);
        } catch (error) {
            showError(t`Could not save address`);
            formErrorHandle(form, error);
        }
    };

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Address`}
                description={t`Your organizer address`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={organizerQuery.isLoading || createLocationMutation.isPending || updateOrganizerLocationMutation.isPending}>
                    {organizerId && (
                        <AddressAutocomplete
                            organizerId={organizerId}
                            onPlaceSelected={handlePlaceSelected}
                            country={form.values.structured_address.country || undefined}
                        />
                    )}
                    <TextInput
                        {...form.getInputProps('structured_address.venue_name')}
                        label={t`Office or venue name`}
                        placeholder={t`Office or venue name`}
                    />
                    <InputGroup>
                        <TextInput
                            {...form.getInputProps('structured_address.address_line_1')}
                            label={t`Address Line 1`}
                            placeholder={t`123 Main Street`}
                        />
                        <TextInput
                            {...form.getInputProps('structured_address.address_line_2')}
                            label={t`Address Line 2`}
                            placeholder={t`Suite 100`}
                        />
                    </InputGroup>
                    <InputGroup>
                        <TextInput
                            {...form.getInputProps('structured_address.city')}
                            label={t`City`}
                            placeholder={t`San Francisco`}
                        />
                        <TextInput
                            {...form.getInputProps('structured_address.state_or_region')}
                            label={t`State or Region`}
                            placeholder={t`California`}
                        />
                    </InputGroup>
                    <InputGroup>
                        <TextInput
                            {...form.getInputProps('structured_address.zip_or_postal_code')}
                            label={t`Zip or Postal Code`}
                            placeholder={t`94103`}
                        />
                        <Select searchable
                                data={countries}
                                {...form.getInputProps('structured_address.country')}
                                label={t`Country`}
                                placeholder={t`United States`}
                        />
                    </InputGroup>

                    <Button loading={createLocationMutation.isPending || updateOrganizerLocationMutation.isPending} type={'submit'}>
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}
