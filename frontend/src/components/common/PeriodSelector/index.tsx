import {useEffect, useState} from "react";
import {Menu, UnstyledButton} from "@mantine/core";
import {IconChevronDown} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import classes from "./PeriodSelector.module.scss";
import {Event} from "../../../types.ts";

export type PeriodPreset =
    | 'today'
    | 'last_7_days'
    | 'last_30_days'
    | 'last_90_days'
    | 'year_to_date'
    | 'event_first_week'
    | 'event_first_month'
    | 'event_first_quarter'
    | 'event_full';

interface PeriodSelectorProps {
    value: PeriodPreset;
    onChange: (preset: PeriodPreset) => void;
    storageKey?: string;
    className?: string;
    event?: Event;
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
        case 'event_first_week':
            return t`First 7 days`;
        case 'event_first_month':
            return t`First 30 days`;
        case 'event_first_quarter':
            return t`First 90 days`;
        case 'event_full':
            return t`Full event`;
    }
};

const ROLLING_PRESETS: PeriodPreset[] = [
    'today',
    'last_7_days',
    'last_30_days',
    'last_90_days',
    'year_to_date',
];

const EVENT_PRESETS: PeriodPreset[] = [
    'event_first_week',
    'event_first_month',
    'event_first_quarter',
    'event_full',
];

const isValidPreset = (raw: string | null, allowed: PeriodPreset[]): raw is PeriodPreset => {
    return raw !== null && (allowed as string[]).includes(raw);
};

export const PeriodSelector = ({value, onChange, storageKey, className = '', event}: PeriodSelectorProps) => {
    const [hydrated, setHydrated] = useState(false);
    const allowed: PeriodPreset[] = event ? [...ROLLING_PRESETS, ...EVENT_PRESETS] : ROLLING_PRESETS;

    useEffect(() => {
        if (hydrated || !storageKey) {
            if (!storageKey) {
                setHydrated(true);
            }
            return;
        }
        try {
            const stored = window.localStorage.getItem(storageKey);
            if (isValidPreset(stored, allowed) && stored !== value) {
                onChange(stored);
            }
        } catch {
            // ignore
        }
        setHydrated(true);
    }, [hydrated, storageKey, value, onChange, allowed]);

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
        <Menu shadow="md" width={200} position="bottom-end" withinPortal>
            <Menu.Target>
                <UnstyledButton className={`${classes.trigger} ${className}`}>
                    <span>{getLabel(value)}</span>
                    <IconChevronDown size={14} className={classes.chevron}/>
                </UnstyledButton>
            </Menu.Target>
            <Menu.Dropdown>
                {event && <Menu.Label className={classes.sectionLabel}>{t`Recent activity`}</Menu.Label>}
                {ROLLING_PRESETS.map((preset) => (
                    <Menu.Item
                        key={preset}
                        onClick={() => handleSelect(preset)}
                        className={value === preset ? classes.selected : ''}
                    >
                        {getLabel(preset)}
                    </Menu.Item>
                ))}
                {event && (
                    <>
                        <Menu.Divider/>
                        <Menu.Label className={classes.sectionLabel}>{t`Event lifetime`}</Menu.Label>
                        {EVENT_PRESETS.map((preset) => (
                            <Menu.Item
                                key={preset}
                                onClick={() => handleSelect(preset)}
                                className={value === preset ? classes.selected : ''}
                            >
                                {getLabel(preset)}
                            </Menu.Item>
                        ))}
                    </>
                )}
            </Menu.Dropdown>
        </Menu>
    );
};

export default PeriodSelector;
