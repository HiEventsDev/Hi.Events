import {t} from "@lingui/macro";
import {Combobox, Loader, TextInput, useCombobox} from "@mantine/core";
import {useRef, useState} from "react";
import {useDebouncedValue} from "@mantine/hooks";
import {GeoPlace, IdParam} from "../../../types.ts";
import {useGeoAutocomplete} from "../../../queries/useGeoAutocomplete.ts";
import {useGeoStatus} from "../../../queries/useGeoStatus.ts";
import {useResolveGeoPlace} from "../../../mutations/useResolveGeoPlace.ts";
import {showError} from "../../../utilites/notifications.tsx";

interface AddressAutocompleteProps {
    organizerId: IdParam;
    label?: string;
    placeholder?: string;
    description?: string;
    country?: string;
    locale?: string;
    onPlaceSelected: (place: GeoPlace) => void;
}

export const AddressAutocomplete = ({
    organizerId,
    label,
    placeholder,
    description,
    country,
    locale,
    onPlaceSelected,
}: AddressAutocompleteProps) => {
    const combobox = useCombobox();
    const [value, setValue] = useState("");
    const [debounced] = useDebouncedValue(value, 250);
    const justSelectedRef = useRef(false);
    const geoStatus = useGeoStatus();
    const geoAvailable = geoStatus.data?.data?.available === true;

    const suggestionsQuery = useGeoAutocomplete(organizerId, debounced, {
        country,
        locale,
        enabled: geoAvailable && debounced.length >= 3 && !justSelectedRef.current,
    });
    const resolvePlace = useResolveGeoPlace();

    if (!geoAvailable) {
        return null;
    }

    const suggestions = suggestionsQuery.data?.data ?? [];

    const handleSelect = async (placeId: string) => {
        const match = suggestions.find((s) => s.provider_place_id === placeId);
        if (!match) return;
        justSelectedRef.current = true;
        combobox.closeDropdown();
        const label = match.secondary_text ? `${match.primary_text}, ${match.secondary_text}` : match.primary_text;
        setValue(label);
        try {
            const response = await resolvePlace.mutateAsync({organizerId, placeId, locale});
            onPlaceSelected(response.data);
        } catch (error) {
            showError(t`Could not retrieve address details`);
        }
    };

    const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const next = event.currentTarget.value;
        justSelectedRef.current = false;
        setValue(next);
        if (next.trim().length >= 3) {
            combobox.openDropdown();
        } else {
            combobox.closeDropdown();
        }
    };

    const handleFocus = () => {
        if (!justSelectedRef.current && value.trim().length >= 3 && suggestions.length > 0) {
            combobox.openDropdown();
        }
    };

    return (
        <Combobox store={combobox} onOptionSubmit={handleSelect} withinPortal>
            <Combobox.Target>
                <TextInput
                    label={label ?? t`Search for an address`}
                    placeholder={placeholder ?? t`Start typing a venue or address...`}
                    description={description}
                    value={value}
                    onChange={handleChange}
                    onFocus={handleFocus}
                    onBlur={() => combobox.closeDropdown()}
                    rightSection={suggestionsQuery.isFetching || resolvePlace.isPending ? <Loader size="xs"/> : null}
                />
            </Combobox.Target>
            <Combobox.Dropdown>
                <Combobox.Options>
                    {suggestions.length === 0 && (
                        <Combobox.Empty>{t`No suggestions`}</Combobox.Empty>
                    )}
                    {suggestions.map((s) => (
                        <Combobox.Option value={s.provider_place_id} key={s.provider_place_id}>
                            <div>
                                <strong>{s.primary_text}</strong>
                                {s.secondary_text && <div style={{fontSize: 12, opacity: 0.7}}>{s.secondary_text}</div>}
                            </div>
                        </Combobox.Option>
                    ))}
                </Combobox.Options>
            </Combobox.Dropdown>
        </Combobox>
    );
};
