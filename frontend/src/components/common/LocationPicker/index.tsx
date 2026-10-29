import {t} from "@lingui/macro";
import {Badge, Button, Combobox, Group, Input, Loader, Select, Text, TextInput, ThemeIcon, useCombobox} from "@mantine/core";
import {useDebouncedValue} from "@mantine/hooks";
import {ReactNode, useEffect, useRef, useState} from "react";
import {IconArrowLeft, IconCheck, IconMapPin, IconPencil, IconSearch} from "@tabler/icons-react";
import countries from "../../../../data/countries.json";
import {GeoPlace, IdParam, Location, VenueAddress} from "../../../types.ts";
import {useGetOrganizerLocations} from "../../../queries/useGetOrganizerLocations.ts";
import {useGeoAutocomplete} from "../../../queries/useGeoAutocomplete.ts";
import {useGeoStatus} from "../../../queries/useGeoStatus.ts";
import {useResolveGeoPlace} from "../../../mutations/useResolveGeoPlace.ts";
import {formatAddress, isAddressSet} from "../../../utilites/addressUtilities.ts";
import {showError} from "../../../utilites/notifications.tsx";
import {InputGroup} from "../InputGroup";
import classes from "./LocationPicker.module.scss";

export type LocationPickerValue =
    | {
    kind: 'saved';
    location: Location;
}
    | {
    kind: 'new';
    address: VenueAddress;
    latitude: number | null;
    longitude: number | null;
    provider: string | null;
    providerPlaceId: string | null;
};

interface LocationPickerProps {
    organizerId: IdParam;
    value: LocationPickerValue | null;
    onChange: (value: LocationPickerValue | null) => void;
    error?: ReactNode;
    clearable?: boolean;
}

const emptyAddress = (): VenueAddress => ({
    venue_name: "",
    address_line_1: "",
    address_line_2: "",
    city: "",
    state_or_region: "",
    zip_or_postal_code: "",
    country: "",
});

const placeToValue = (place: GeoPlace): LocationPickerValue => ({
    kind: 'new',
    address: {
        venue_name: place.address.venue_name ?? "",
        address_line_1: place.address.address_line_1 ?? "",
        address_line_2: place.address.address_line_2 ?? "",
        city: place.address.city ?? "",
        state_or_region: place.address.state_or_region ?? "",
        zip_or_postal_code: place.address.zip_or_postal_code ?? "",
        country: place.address.country ?? "",
    },
    latitude: place.latitude ?? null,
    longitude: place.longitude ?? null,
    provider: place.provider,
    providerPlaceId: place.provider_place_id,
});

const locationLabel = (location: Location) =>
    location.name || location.structured_address?.venue_name || formatAddress(location.structured_address ?? {}) || t`Unnamed location`;

const matchesQuery = (location: Location, query: string) => {
    const needle = query.trim().toLowerCase();
    if (needle === '') {
        return true;
    }

    return [
        location.name,
        location.structured_address?.venue_name,
        location.structured_address?.address_line_1,
        location.structured_address?.city,
    ].some((field) => field?.toLowerCase().includes(needle));
};

export const LocationPicker = ({organizerId, value, onChange, error, clearable = false}: LocationPickerProps) => {
    const [view, setView] = useState<'card' | 'search' | 'manual'>(value ? 'card' : 'search');
    const touchedRef = useRef(false);
    const [query, setQuery] = useState("");
    const [debouncedQuery] = useDebouncedValue(query, 250);
    const combobox = useCombobox();

    const geoStatus = useGeoStatus();
    const geoAvailable = geoStatus.data?.data?.available === true;
    const resolvePlace = useResolveGeoPlace();

    const savedLocationsQuery = useGetOrganizerLocations(
        organizerId,
        {perPage: 100, sortBy: 'name', sortDirection: 'asc'},
        !!organizerId,
    );
    const savedLocations = savedLocationsQuery.data?.data ?? [];

    const suggestionsQuery = useGeoAutocomplete(organizerId, debouncedQuery, {
        enabled: geoAvailable && view === 'search' && debouncedQuery.trim().length >= 3,
    });
    const suggestions = suggestionsQuery.data?.data ?? [];

    useEffect(() => {
        if (value && !touchedRef.current && view === 'search') {
            setView('card');
        }
    }, [value === null]);

    const savedMatches = savedLocations.filter((location) => matchesQuery(location, query)).slice(0, 5);

    const startSearch = () => {
        touchedRef.current = true;
        setQuery("");
        setView('search');
    };

    const startManual = () => {
        touchedRef.current = true;
        if (value?.kind !== 'new') {
            onChange({
                kind: 'new',
                address: emptyAddress(),
                latitude: null,
                longitude: null,
                provider: null,
                providerPlaceId: null,
            });
        }
        setView('manual');
    };

    const handleOptionSubmit = async (optionValue: string) => {
        touchedRef.current = true;
        combobox.closeDropdown();

        if (optionValue.startsWith('saved-')) {
            const location = savedLocations.find((candidate) => String(candidate.id) === optionValue.slice('saved-'.length));
            if (location) {
                onChange({kind: 'saved', location});
                setView('card');
            }
            return;
        }

        try {
            const response = await resolvePlace.mutateAsync({organizerId, placeId: optionValue});
            onChange(placeToValue(response.data));
            setView('card');
        } catch {
            showError(t`Could not retrieve address details`);
        }
    };

    const updateAddress = (field: keyof VenueAddress, fieldValue: string | null) => {
        if (value?.kind !== 'new') {
            return;
        }
        onChange({...value, address: {...value.address, [field]: fieldValue ?? ""}});
    };

    if (view === 'card' && value) {
        const isSaved = value.kind === 'saved';
        const address = isSaved ? value.location.structured_address ?? {} : value.address;
        const title = isSaved
            ? locationLabel(value.location)
            : (value.address.venue_name || value.address.address_line_1 || formatAddress(value.address) || t`New location`);
        const addressLine = isAddressSet(address) ? formatAddress(address) : null;

        return (
            <Input.Wrapper label={t`Location`} error={error}>
                <div className={`${classes.panel} ${classes.card}`}>
                    <Group gap="sm" wrap="nowrap" className={classes.cardDetails}>
                        <ThemeIcon variant="light" radius="xl">
                            <IconMapPin size={16}/>
                        </ThemeIcon>
                        <div className={classes.cardText}>
                            <Group gap="xs" wrap="nowrap">
                                <Text fw={600} truncate className={classes.cardTitle}>{title}</Text>
                                <Badge size="xs" variant="light" color={isSaved ? 'blue' : 'green'} className={classes.cardBadge}>
                                    {isSaved ? t`Saved location` : t`New location`}
                                </Badge>
                            </Group>
                            {addressLine && (
                                <Text size="sm" c="dimmed" truncate>{addressLine}</Text>
                            )}
                        </div>
                    </Group>
                    <Group gap={6} className={classes.cardActions}>
                        {value.kind === 'new' && (
                            <Button variant="default" size="xs" leftSection={<IconPencil size={14}/>} onClick={() => {
                                touchedRef.current = true;
                                setView('manual');
                            }}>
                                {t`Edit`}
                            </Button>
                        )}
                        <Button variant="default" size="xs" leftSection={<IconSearch size={14}/>} onClick={startSearch}>
                            {t`Change`}
                        </Button>
                        {clearable && (
                            <Button variant="light" color="red" size="xs" onClick={() => {
                                touchedRef.current = true;
                                onChange(null);
                                setQuery("");
                                setView('search');
                            }}>
                                {t`Remove`}
                            </Button>
                        )}
                    </Group>
                </div>
            </Input.Wrapper>
        );
    }

    if (view === 'manual' && value?.kind === 'new') {
        return (
            <Input.Wrapper label={t`Location`} error={error}>
                <div className={classes.panel}>
                    <Group gap="xs" mb="sm">
                        <ThemeIcon variant="light" size="sm" radius="xl">
                            <IconMapPin size={13}/>
                        </ThemeIcon>
                        <Text fw={600} size="sm">{t`New location`}</Text>
                    </Group>
                    <TextInput
                        label={t`Venue Name`}
                        placeholder={t`Conference Center`}
                        value={value.address.venue_name ?? ""}
                        onChange={(event) => updateAddress('venue_name', event.currentTarget.value)}
                    />
                    <InputGroup>
                        <TextInput
                            label={t`Address Line 1`}
                            value={value.address.address_line_1 ?? ""}
                            onChange={(event) => updateAddress('address_line_1', event.currentTarget.value)}
                        />
                        <TextInput
                            label={t`Address Line 2`}
                            value={value.address.address_line_2 ?? ""}
                            onChange={(event) => updateAddress('address_line_2', event.currentTarget.value)}
                        />
                    </InputGroup>
                    <InputGroup>
                        <TextInput
                            label={t`City`}
                            value={value.address.city ?? ""}
                            onChange={(event) => updateAddress('city', event.currentTarget.value)}
                        />
                        <TextInput
                            label={t`State or Region`}
                            value={value.address.state_or_region ?? ""}
                            onChange={(event) => updateAddress('state_or_region', event.currentTarget.value)}
                        />
                    </InputGroup>
                    <InputGroup>
                        <TextInput
                            label={t`Zip or Postal Code`}
                            value={value.address.zip_or_postal_code ?? ""}
                            onChange={(event) => updateAddress('zip_or_postal_code', event.currentTarget.value)}
                        />
                        <Select
                            searchable
                            data={countries}
                            label={t`Country`}
                            placeholder={t`United States`}
                            value={value.address.country || null}
                            onChange={(country) => updateAddress('country', country)}
                        />
                    </InputGroup>
                    <Group justify="space-between" mt="sm">
                        <Button variant="default" size="xs" leftSection={<IconArrowLeft size={14}/>} onClick={startSearch}>
                            {t`Back to search`}
                        </Button>
                        <Button
                            variant="light"
                            size="xs"
                            leftSection={<IconCheck size={14}/>}
                            disabled={!isAddressSet(value.address)}
                            onClick={() => setView('card')}
                        >
                            {t`Use this address`}
                        </Button>
                    </Group>
                </div>
            </Input.Wrapper>
        );
    }

    return (
        <Input.Wrapper label={t`Location`} error={error}>
            <Combobox store={combobox} onOptionSubmit={handleOptionSubmit} withinPortal>
                <Combobox.Target>
                    <TextInput
                        placeholder={t`Search saved locations or find an address...`}
                        value={query}
                        leftSection={<IconSearch size={16}/>}
                        rightSection={(suggestionsQuery.isFetching || resolvePlace.isPending) ? <Loader size="xs"/> : null}
                        onChange={(event) => {
                            touchedRef.current = true;
                            setQuery(event.currentTarget.value);
                            combobox.openDropdown();
                        }}
                        onFocus={() => combobox.openDropdown()}
                        onBlur={() => combobox.closeDropdown()}
                    />
                </Combobox.Target>
                <Combobox.Dropdown>
                    <Combobox.Options>
                        {savedMatches.length > 0 && (
                            <Combobox.Group label={t`Saved locations`}>
                                {savedMatches.map((location) => (
                                    <Combobox.Option value={`saved-${location.id}`} key={String(location.id)}>
                                        <Text size="sm" fw={500}>{locationLabel(location)}</Text>
                                        {location.structured_address && isAddressSet(location.structured_address) && (
                                            <Text size="xs" c="dimmed">{formatAddress(location.structured_address)}</Text>
                                        )}
                                    </Combobox.Option>
                                ))}
                            </Combobox.Group>
                        )}
                        {suggestions.length > 0 && (
                            <Combobox.Group label={t`Search results`}>
                                {suggestions.map((suggestion) => (
                                    <Combobox.Option value={suggestion.provider_place_id} key={suggestion.provider_place_id}>
                                        <Text size="sm" fw={500}>{suggestion.primary_text}</Text>
                                        {suggestion.secondary_text && (
                                            <Text size="xs" c="dimmed">{suggestion.secondary_text}</Text>
                                        )}
                                    </Combobox.Option>
                                ))}
                            </Combobox.Group>
                        )}
                        {(savedMatches.length === 0 && suggestions.length === 0) && (
                            <Combobox.Empty>{t`No suggestions`}</Combobox.Empty>
                        )}
                    </Combobox.Options>
                </Combobox.Dropdown>
            </Combobox>
            <Group justify="space-between" mt="xs">
                <Button variant="default" size="xs" leftSection={<IconPencil size={14}/>} onClick={startManual}>
                    {t`Enter address manually`}
                </Button>
                {value && (
                    <Button variant="default" size="xs" onClick={() => {
                        touchedRef.current = true;
                        setView('card');
                    }}>
                        {t`Cancel`}
                    </Button>
                )}
            </Group>
        </Input.Wrapper>
    );
};
