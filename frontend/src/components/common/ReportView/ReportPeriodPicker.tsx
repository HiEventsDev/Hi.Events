import {useState} from "react";
import {Button, Menu} from "@mantine/core";
import {DatePickerInput, DatesRangeValue} from "@mantine/dates";
import {IconCalendar, IconCheck, IconChevronDown} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import dayjs from "dayjs";
import '@mantine/dates/styles.css';
import {ReportPeriod, REPORT_PERIODS, WallDateRange} from "../../../utilites/reportPeriod.ts";
import {wallDateInTimezone} from "../../../utilites/dates.ts";
import classes from "./ReportView.module.scss";

const periodLabel = (period: ReportPeriod): string => ({
    'today': t`Today`,
    '7d': t`Last 7 days`,
    '14d': t`Last 14 days`,
    '30d': t`Last 30 days`,
    '90d': t`Last 90 days`,
    '6m': t`Last 6 months`,
    'ytd': t`Year to date`,
    '12m': t`Last 12 months`,
    'custom': t`Custom range`,
}[period]);

interface ReportPeriodPickerProps {
    period: ReportPeriod;
    range: WallDateRange;
    timezone: string;
    onPeriodChange: (period: ReportPeriod) => void;
    onCustomRangeChange: (range: WallDateRange) => void;
}

export const ReportPeriodPicker = ({period, range, timezone, onPeriodChange, onCustomRangeChange}: ReportPeriodPickerProps) => {
    const currentLabel = periodLabel(period);
    const [draft, setDraft] = useState<DatesRangeValue<string> | null>(null);

    const handleRangeChange = (next: DatesRangeValue<string>) => {
        const [from, to] = next;
        if (from && to) {
            setDraft(null);
            onCustomRangeChange({from, to});
            return;
        }
        setDraft(next);
    };

    return (
        <>
            <Menu position="bottom-start" width={200} withinPortal>
                <Menu.Target>
                    <Button
                        variant="default"
                        leftSection={<IconCalendar size={16} stroke={1.5}/>}
                        rightSection={<IconChevronDown size={14} stroke={1.5}/>}
                        aria-label={t`Reporting period: ${currentLabel}`}
                        data-testid="report-period-select"
                    >
                        {currentLabel}
                    </Button>
                </Menu.Target>
                <Menu.Dropdown>
                    {REPORT_PERIODS.map(option => (
                        <Menu.Item
                            key={option}
                            onClick={() => onPeriodChange(option)}
                            rightSection={option === period ? <IconCheck size={14}/> : null}
                            aria-current={option === period || undefined}
                        >
                            {periodLabel(option)}
                        </Menu.Item>
                    ))}
                </Menu.Dropdown>
            </Menu>
            {period === 'custom' && (
                <DatePickerInput
                    type="range"
                    className={classes.rangeInput}
                    aria-label={t`Custom date range`}
                    value={draft ?? [range.from, range.to]}
                    onChange={handleRangeChange}
                    onDropdownClose={() => setDraft(null)}
                    minDate={wallDateInTimezone(dayjs().subtract(1, 'year').toDate(), timezone)}
                    maxDate={wallDateInTimezone(new Date(), timezone)}
                    popoverProps={{position: 'bottom-start'}}
                    data-testid="report-date-range-picker"
                />
            )}
        </>
    );
};
