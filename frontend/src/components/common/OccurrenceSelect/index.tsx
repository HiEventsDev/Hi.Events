import {CSSProperties, useMemo, useState} from "react";
import {Combobox, InputBase, ScrollArea, Text, useCombobox} from "@mantine/core";
import {IconCalendar, IconSearch} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {EventOccurrence} from "../../../types.ts";
import {filterAndGroupOccurrences, formatOccurrenceLabel, isToday} from "./occurrenceSelectUtils.ts";

interface OccurrenceSelectProps {
    occurrences: EventOccurrence[];
    timezone: string;
    value: string | null;
    onChange: (value: string | null) => void;
    placeholder?: string;
    clearable?: boolean;
    label?: string;
    description?: string;
    size?: 'xs' | 'sm' | 'md';
    allLabel?: string;
    filterCancelled?: boolean;
    style?: CSSProperties;
}

const MAX_VISIBLE = 50;

export const OccurrenceSelect = ({
    occurrences,
    timezone: tz,
    value,
    onChange,
    placeholder,
    clearable = false,
    label,
    description,
    size = 'sm',
    allLabel,
    filterCancelled = true,
    style,
}: OccurrenceSelectProps) => {
    const [search, setSearch] = useState('');
    const combobox = useCombobox({
        onDropdownClose: () => {
            setSearch('');
            combobox.resetSelectedOption();
        },
        onDropdownOpen: () => {
            combobox.focusSearchInput();
        },
    });

    const {grouped, totalFiltered, totalAvailable} = useMemo(
        () => filterAndGroupOccurrences(occurrences, {
            search,
            tz,
            filterCancelled,
            maxVisible: MAX_VISIBLE,
        }),
        [occurrences, tz, search, filterCancelled],
    );

    const selectedOcc = value
        ? occurrences.find(o => String(o.id) === value)
        : null;

    const displayValue = selectedOcc
        ? formatOccurrenceLabel(selectedOcc, tz)
        : (value === '' && allLabel) ? allLabel : null;

    return (
        <div style={{width: 275, ...style}}>
        <Combobox
            store={combobox}
            onOptionSubmit={(val) => {
                if (val === '__all__') {
                    onChange('');
                } else if (val === '__clear__') {
                    onChange(null);
                } else {
                    onChange(val);
                }
                combobox.closeDropdown();
            }}
        >
            <Combobox.Target>
                <InputBase
                    component="button"
                    type="button"
                    pointer
                    label={label}
                    description={description}
                    size={size}
                    mb={0}
                    styles={{input: {minWidth: 0, width: '100%'}}}
                    leftSection={<IconCalendar size={14}/>}
                    rightSection={<Combobox.Chevron/>}
                    rightSectionPointerEvents="none"
                    onClick={() => combobox.toggleDropdown()}
                >
                    <span style={{
                        display: 'block',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                    }}>
                        {displayValue || (
                            <span style={{color: 'var(--mantine-color-placeholder)'}}>
                                {placeholder || t`Select occurrence`}
                            </span>
                        )}
                    </span>
                </InputBase>
            </Combobox.Target>

            <Combobox.Dropdown>
                <Combobox.Search
                    value={search}
                    onChange={(event) => setSearch(event.currentTarget.value)}
                    placeholder={t`Search dates...`}
                    leftSection={<IconSearch size={14}/>}
                />
                <Combobox.Options>
                    <ScrollArea.Autosize mah={280} type="scroll">
                        {allLabel && (
                            <Combobox.Option
                                value="__all__"
                                active={value === ''}
                            >
                                {allLabel}
                            </Combobox.Option>
                        )}

                        {clearable && value && (
                            <Combobox.Option value="__clear__">
                                <Text size="sm" c="dimmed">{t`Clear`}</Text>
                            </Combobox.Option>
                        )}

                        {grouped.map(group => (
                            <Combobox.Group key={group.key} label={group.label}>
                                {group.items.map(occ => {
                                    const isTodayOcc = isToday(occ, tz);
                                    return (
                                        <Combobox.Option
                                            key={occ.id}
                                            value={String(occ.id)}
                                            active={value === String(occ.id)}
                                        >
                                            <Text size="sm" fw={isTodayOcc ? 700 : 400}>
                                                {isTodayOcc && `${t`Today`} — `}
                                                {formatOccurrenceLabel(occ, tz)}
                                            </Text>
                                        </Combobox.Option>
                                    );
                                })}
                            </Combobox.Group>
                        ))}

                        {totalFiltered === 0 && (
                            <Combobox.Empty>{t`No dates match your search`}</Combobox.Empty>
                        )}

                        {totalFiltered >= MAX_VISIBLE && totalAvailable > MAX_VISIBLE && (
                            <Text size="xs" c="dimmed" ta="center" py={8}>
                                {t`Showing ${MAX_VISIBLE} of ${totalAvailable} dates. Type to search.`}
                            </Text>
                        )}
                    </ScrollArea.Autosize>
                </Combobox.Options>
            </Combobox.Dropdown>
        </Combobox>
        </div>
    );
};
