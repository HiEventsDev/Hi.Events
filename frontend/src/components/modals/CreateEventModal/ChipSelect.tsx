import {ReactNode, useMemo, useState} from "react";
import {Combobox, ScrollArea, Text, UnstyledButton, useCombobox} from "@mantine/core";
import {IconCheck, IconChevronDown, IconPlus} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import classes from "./CreateEventModal.module.scss";
import {PropertyChip} from "./PropertyChip.tsx";

export interface ChipSelectOption {
    value: string;
    label: string;
    description?: string;
}

interface ChipSelectAction {
    label: string;
    onSelect: () => void;
    dataTestId?: string;
}

interface ChipSelectProps {
    data: ChipSelectOption[];
    value?: string | null;
    onChange: (value: string) => void;
    placeholder: string;
    ariaLabel: string;
    dataTestId: string;
    icon?: ReactNode;
    searchable?: boolean;
    onClear?: () => void;
    action?: ChipSelectAction;
    variant?: 'chip' | 'crumb';
    invalid?: boolean;
}

const ACTION_VALUE = '__chip_select_action__';

export const ChipSelect = ({
                               data,
                               value,
                               onChange,
                               placeholder,
                               ariaLabel,
                               dataTestId,
                               icon,
                               searchable = false,
                               onClear,
                               action,
                               variant = 'chip',
                               invalid = false,
                           }: ChipSelectProps) => {
    const [search, setSearch] = useState('');
    const combobox = useCombobox({
        onDropdownClose: () => {
            combobox.resetSelectedOption();
            setSearch('');
        },
        onDropdownOpen: () => {
            if (searchable) {
                combobox.focusSearchInput();
            }
        },
    });

    const selected = data.find((option) => option.value === value);

    const filtered = useMemo(() => {
        const normalise = (text: string) => text.toLowerCase().replace(/_/g, ' ');
        const query = normalise(search.trim());
        if (!query) {
            return data;
        }
        return data.filter((option) =>
            [option.label, option.value, option.description ?? ''].some((text) => normalise(text).includes(query))
        );
    }, [data, search]);

    const target = variant === 'crumb' ? (
        <UnstyledButton
            type="button"
            className={invalid ? `${classes.crumb} ${classes.crumbInvalid}` : classes.crumb}
            onClick={() => combobox.toggleDropdown()}
            aria-label={ariaLabel}
            data-testid={dataTestId}
        >
            {selected ? selected.label : placeholder}
            <IconChevronDown size={14}/>
        </UnstyledButton>
    ) : (
        <PropertyChip
            icon={icon}
            empty={!selected}
            invalid={invalid}
            onClick={() => combobox.toggleDropdown()}
            onClear={selected ? onClear : undefined}
            clearLabel={t`Clear`}
            clearTestId={`${dataTestId}-clear`}
            aria-label={ariaLabel}
            data-testid={dataTestId}
        >
            {selected ? selected.label : placeholder}
        </PropertyChip>
    );

    return (
        <Combobox
            store={combobox}
            width={280}
            position="bottom-start"
            shadow="md"
            onOptionSubmit={(optionValue) => {
                combobox.closeDropdown();
                if (optionValue === ACTION_VALUE) {
                    action?.onSelect();
                    return;
                }
                onChange(optionValue);
            }}
        >
            <Combobox.Target targetType="button">
                {target}
            </Combobox.Target>

            <Combobox.Dropdown>
                {searchable && (
                    <Combobox.Search
                        value={search}
                        onChange={(event) => setSearch(event.currentTarget.value)}
                        placeholder={t`Search`}
                        aria-label={t`Search`}
                    />
                )}
                <Combobox.Options>
                    <ScrollArea.Autosize mah={260} type="scroll">
                        {filtered.length === 0 && <Combobox.Empty>{t`No matches`}</Combobox.Empty>}
                        {filtered.map((option) => (
                            <Combobox.Option
                                value={option.value}
                                key={option.value}
                                active={option.value === value}
                                data-testid={`${dataTestId}-option-${option.value}`}
                                className={classes.option}
                            >
                                <span className={classes.optionCheck}>
                                    {option.value === value && <IconCheck size={14}/>}
                                </span>
                                <span className={classes.optionLabel}>{option.label}</span>
                                {option.description && (
                                    <Text span size="xs" c="dimmed" className={classes.optionDescription}>
                                        {option.description}
                                    </Text>
                                )}
                            </Combobox.Option>
                        ))}
                    </ScrollArea.Autosize>
                    {action && (
                        <div className={classes.optionFooter}>
                            <Combobox.Option
                                value={ACTION_VALUE}
                                className={classes.option}
                                data-testid={action.dataTestId}
                            >
                                <span className={classes.optionCheck}><IconPlus size={14}/></span>
                                <span className={classes.optionLabel}>{action.label}</span>
                            </Combobox.Option>
                        </div>
                    )}
                </Combobox.Options>
            </Combobox.Dropdown>
        </Combobox>
    );
};
