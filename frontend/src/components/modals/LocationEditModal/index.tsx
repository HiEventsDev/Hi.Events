import {t} from "@lingui/macro";
import {Select, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useState} from "react";
import {Modal} from "../../common/Modal";
import {Button} from "../../common/Button";
import {InputGroup} from "../../common/InputGroup";
import {AddressAutocomplete} from "../../common/AddressAutocomplete";
import countries from "../../../../data/countries.json";
import {GeoPlace, IdParam, Location, VenueAddress} from "../../../types.ts";
import {useCreateLocation} from "../../../mutations/useCreateLocation.ts";
import {useUpdateLocation} from "../../../mutations/useUpdateLocation.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";

interface LocationEditModalProps {
    onClose: () => void;
    organizerId: IdParam;
    location: Location | null;
}

const hasAnyAddressField = (address: VenueAddress) => [
    address.venue_name,
    address.address_line_1,
    address.city,
    address.state_or_region,
    address.zip_or_postal_code,
    address.country,
].some((value) => (value ?? '').trim().length > 0);

export const LocationEditModal = ({onClose, organizerId, location}: LocationEditModalProps) => {
    const isEditing = !!location?.id;
    const createMutation = useCreateLocation();
    const updateMutation = useUpdateLocation();
    const formErrorHandle = useFormErrorResponseHandler();
    const isPending = createMutation.isPending || updateMutation.isPending;
    const [latLng, setLatLng] = useState<{lat: number | null; lng: number | null; provider: string | null; placeId: string | null}>({
        lat: location?.latitude ?? null,
        lng: location?.longitude ?? null,
        provider: location?.provider ?? null,
        placeId: location?.provider_place_id ?? null,
    });

    const form = useForm<{name: string; structured_address: VenueAddress}>({
        initialValues: {
            name: location?.name ?? "",
            structured_address: {
                venue_name: location?.structured_address?.venue_name ?? "",
                address_line_1: location?.structured_address?.address_line_1 ?? "",
                address_line_2: location?.structured_address?.address_line_2 ?? "",
                city: location?.structured_address?.city ?? "",
                state_or_region: location?.structured_address?.state_or_region ?? "",
                zip_or_postal_code: location?.structured_address?.zip_or_postal_code ?? "",
                country: location?.structured_address?.country ?? "",
            },
        },
        validate: {
            structured_address: {
                venue_name: (_, values) => hasAnyAddressField(values.structured_address)
                    ? null
                    : t`Provide at least one address field (venue, street, city, or country).`,
            },
        },
    });

    const handlePlaceSelected = (place: GeoPlace) => {
        form.setValues({
            name: place.display_name ?? place.address.venue_name ?? form.values.name,
            structured_address: {
                venue_name: place.address.venue_name ?? "",
                address_line_1: place.address.address_line_1 ?? "",
                address_line_2: place.address.address_line_2 ?? "",
                city: place.address.city ?? "",
                state_or_region: place.address.state_or_region ?? "",
                zip_or_postal_code: place.address.zip_or_postal_code ?? "",
                country: place.address.country ?? "",
            },
        });
        form.clearErrors();
        setLatLng({
            lat: place.latitude ?? null,
            lng: place.longitude ?? null,
            provider: place.provider,
            placeId: place.provider_place_id,
        });
    };

    const handleSubmit = async (values: {name: string; structured_address: VenueAddress}) => {
        const payload = {
            name: values.name || null,
            structured_address: values.structured_address,
            latitude: latLng.lat,
            longitude: latLng.lng,
            provider: latLng.provider,
            provider_place_id: latLng.placeId,
        };
        try {
            if (isEditing && location?.id) {
                await updateMutation.mutateAsync({organizerId, locationId: location.id, payload});
                showSuccess(t`Location updated`);
            } else {
                await createMutation.mutateAsync({organizerId, payload});
                showSuccess(t`Location saved`);
            }
            onClose();
        } catch (error: any) {
            if (error?.response?.status === 409 && error?.response?.data?.message) {
                showError(error.response.data.message);
                return;
            }

            formErrorHandle(form, error);

            const addressError = error?.response?.data?.errors?.structured_address;
            if (addressError) {
                form.setFieldError(
                    'structured_address.venue_name',
                    Array.isArray(addressError) ? addressError[0] : addressError,
                );
            }
        }
    };

    return (
        <Modal opened onClose={onClose} heading={isEditing ? t`Edit Location` : t`Add Location`} size="lg">
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <AddressAutocomplete
                    organizerId={organizerId}
                    country={form.values.structured_address.country || undefined}
                    onPlaceSelected={handlePlaceSelected}
                />
                <TextInput
                    {...form.getInputProps("name")}
                    label={t`Display name`}
                    description={t`Optional nickname shown in pickers, e.g. "HQ Conference Room"`}
                    placeholder={t`Main Office`}
                />
                <TextInput
                    {...form.getInputProps("structured_address.venue_name")}
                    label={t`Venue Name`}
                    placeholder={t`Conference Center`}
                />
                <InputGroup>
                    <TextInput {...form.getInputProps("structured_address.address_line_1")} label={t`Address Line 1`}/>
                    <TextInput {...form.getInputProps("structured_address.address_line_2")} label={t`Address Line 2`}/>
                </InputGroup>
                <InputGroup>
                    <TextInput {...form.getInputProps("structured_address.city")} label={t`City`}/>
                    <TextInput {...form.getInputProps("structured_address.state_or_region")} label={t`State or Region`}/>
                </InputGroup>
                <InputGroup>
                    <TextInput {...form.getInputProps("structured_address.zip_or_postal_code")} label={t`Zip or Postal Code`}/>
                    <Select
                        searchable
                        data={countries}
                        {...form.getInputProps("structured_address.country")}
                        label={t`Country`}
                        placeholder={t`United States`}
                    />
                </InputGroup>
                <Button type="submit" fullWidth mt="xl" loading={isPending}>
                    {isEditing ? t`Save Changes` : t`Add Location`}
                </Button>
            </form>
        </Modal>
    );
};
