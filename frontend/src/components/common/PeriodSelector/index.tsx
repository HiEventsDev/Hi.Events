import {useEffect, useState} from "react";
import {Menu, UnstyledButton} from "@mantine/core";
import {IconChevronDown} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import classes from "./PeriodSelector.module.scss";

export type PeriodPreset =
    | 'today'
    | 'last_7_days'
    | 'last_30_days'
    | 'last_90_days'
    | 'year_to_date';

interface PeriodSelectorProps {
    value: PeriodPreset;
    onChange: (preset: PeriodPreset) => void;
    storageKey?: string;
    className?: string;
}

const getLabel = (preset: PeriodPreset): string => {
    switch (preset) {
        case 'today':
            return t`Today`;
        case 'last_7_days':
            return t`Last 7 days`;
        case 'last_30_days':
            return t`Last 30 days`;
        case 'last_90_days':
            return t`Last 90 days`;
        case 'year_to_date':
            return t`Year to date`;
    }
};

const VALID_PRESETS: PeriodPreset[] = [
    'today',
    'last_7_days',
    'last_30_days',
    'last_90_days',
    'year_to_date',
];

const isValidPreset = (raw: string | null): raw is PeriodPreset => {
    return raw !== null && (VALID_PRESETS as string[]).includes(raw);
};

export const PeriodSelector = ({value, onChange, storageKey, className = ''}: PeriodSelectorProps) => {
    const [hydrated, setHydrated] = useState(false);

    useEffect(() => {
        if (hydrated || !storageKey) {
            if (!storageKey) {
                setHydrated(true);
            }
            return;
        }
        try {
            const stored = window.localStorage.getItem(storageKey);
            if (isValidPreset(stored) && stored !== value) {
                onChange(stored);
            }
        } catch {
            // ignore
        }
        setHydrated(true);
    }, [hydrated, storageKey, value, onChange]);

    const handleSelect = (preset: PeriodPreset) => {
        onChange(preset);
        if (storageKey && typeof window !== 'undefined') {
            try {
                window.localStorage.setItem(storageKey, preset);
            } catch {
                // ignore
            }
        }
    };

    return (
        <Menu shadow="md" width={180} position="bottom-end" withinPortal>
            <Menu.Target>
                <UnstyledButton className={`${classes.trigger} ${className}`}>
                    <span>{getLabel(value)}</span>
                    <IconChevronDown size={14} className={classes.chevron}/>
                </UnstyledButton>
            </Menu.Target>
            <Menu.Dropdown>
                {VALID_PRESETS.map((preset) => (
                    <Menu.Item
                        key={preset}
                        onClick={() => handleSelect(preset)}
                        className={value === preset ? classes.selected : ''}
                    >
                        {getLabel(preset)}
                    </Menu.Item>
                ))}
            </Menu.Dropdown>
        </Menu>
    );
};

export default PeriodSelector;
