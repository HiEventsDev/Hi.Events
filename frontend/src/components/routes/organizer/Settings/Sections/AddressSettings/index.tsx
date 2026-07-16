import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetOrganizer} from "../../../../../../queries/useGetOrganizer.ts";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {LocationPicker, LocationPickerValue} from "../../../../../common/LocationPicker";
import {useCreateLocation} from "../../../../../../mutations/useCreateLocation.ts";
import {useUpdateOrganizerLocation} from "../../../../../../mutations/useUpdateOrganizerLocation.ts";
import {isAddressSet, sameAddress} from "../../../../../../utilites/addressUtilities.ts";

export const AddressSettings = () => {
    const {organizerId} = useParams();
    const organizerQuery = useGetOrganizer(organizerId);
    const createLocationMutation = useCreateLocation();
    const updateOrganizerLocationMutation = useUpdateOrganizerLocation();

    const form = useForm({
        initialValues: {
            location: null as LocationPickerValue | null,
        },
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (organizerQuery?.isFetched && organizerQuery.data) {
            const organizer = organizerQuery.data;
            form.setValues({
                location: organizer.location_id && organizer.location
                    ? {kind: 'saved', location: {...organizer.location, id: organizer.location.id ?? organizer.location_id}}
                    : null,
            });
        }
    }, [organizerQuery.isFetched]);

    const resolveLocationId = async (value: LocationPickerValue): Promise<number | null> => {
        if (value.kind === 'saved') {
            return value.location.id ? Number(value.location.id) : null;
        }

        if (!organizerId || !isAddressSet(value.address)) {
            return null;
        }

        const existingLocationId = organizerQuery.data?.location_id ?? null;
        const existingAddress = organizerQuery.data?.location?.structured_address ?? null;
        if (existingLocationId !== null && existingAddress !== null && sameAddress(existingAddress, value.address)) {
            return Number(existingLocationId);
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

    const handleSubmit = async (values: {location: LocationPickerValue | null}) => {
        if (!organizerId) {
            return;
        }

        try {
            const locationId = values.location ? await resolveLocationId(values.location) : null;
            const existingLocationId = organizerQuery.data?.location_id ?? null;

            if (locationId !== null || existingLocationId !== null) {
                await updateOrganizerLocationMutation.mutateAsync({
                    organizerId,
                    locationId,
                });
            }

            showSuccess(t`Successfully Updated Address`);
        } catch (error) {
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
                        <LocationPicker
                            organizerId={organizerId}
                            value={form.values.location}
                            onChange={(value) => form.setFieldValue('location', value)}
                            clearable
                        />
                    )}

                    <Button loading={createLocationMutation.isPending || updateOrganizerLocationMutation.isPending} type={'submit'}>
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}
