import {ReactNode, useState} from "react";
import {Popover} from "@mantine/core";
import {DatePicker, TimePicker} from "@mantine/dates";
import {t} from "@lingui/macro";
import dayjs from "dayjs";
import classes from "./CreateEventModal.module.scss";
import {PropertyChip} from "./PropertyChip.tsx";
import {NAIVE_DATE_TIME_FORMAT, uses12HourClock} from "./dateTimeFormat.ts";

interface DateTimeChipProps {
    value?: string | null;
    onChange: (value: string) => void;
    label: ReactNode;
    ariaLabel: string;
    dataTestId: string;
    icon?: ReactNode;
    empty?: boolean;
    invalid?: boolean;
    minDate?: string | null;
    seedValue?: () => string;
    onClear?: () => void;
}

// eslint-disable-next-line lingui/no-unlocalized-strings
const DATE_FORMAT = 'YYYY-MM-DD';
// eslint-disable-next-line lingui/no-unlocalized-strings
const TIME_FORMAT = 'HH:mm:ss';

export const DateTimeChip = ({
                                 value,
                                 onChange,
                                 label,
                                 ariaLabel,
                                 dataTestId,
                                 icon,
                                 empty,
                                 invalid,
                                 minDate,
                                 seedValue,
                                 onClear,
                             }: DateTimeChipProps) => {
    const [opened, setOpened] = useState(false);
    const current = value ? dayjs(value) : null;

    const update = (date: string, time: string) => {
        const next = dayjs(`${date} ${time}`);
        if (next.isValid()) {
            onChange(next.format(NAIVE_DATE_TIME_FORMAT));
        }
    };

    const toggle = () => {
        if (!opened && !value && seedValue) {
            onChange(seedValue());
        }
        setOpened((isOpen) => !isOpen);
    };

    return (
        <Popover opened={opened} onChange={setOpened} position="bottom-start" shadow="md" trapFocus>
            <Popover.Target>
                <PropertyChip
                    icon={icon}
                    empty={empty}
                    invalid={invalid}
                    onClick={toggle}
                    onClear={onClear}
                    clearLabel={t`Clear`}
                    clearTestId={`${dataTestId}-clear`}
                    aria-label={ariaLabel}
                    aria-expanded={opened}
                    data-testid={dataTestId}
                >
                    {label}
                </PropertyChip>
            </Popover.Target>
            <Popover.Dropdown className={classes.datePopover} data-testid={`${dataTestId}-popover`}>
                <DatePicker
                    value={current ? current.format(DATE_FORMAT) : null}
                    minDate={minDate ? dayjs(minDate).format(DATE_FORMAT) : undefined}
                    defaultDate={current ? current.format(DATE_FORMAT) : undefined}
                    onChange={(date) => {
                        if (date) {
                            update(dayjs(date).format(DATE_FORMAT), current ? current.format(TIME_FORMAT) : '21:00:00');
                        }
                    }}
                />
                <div className={classes.timeRow}>
                    <span className={classes.timeLabel}>{t`Time`}</span>
                    <TimePicker
                        value={current ? current.format(TIME_FORMAT) : ''}
                        format={uses12HourClock() ? '12h' : '24h'}
                        aria-label={t`Time`}
                        hoursInputLabel={t`Hours`}
                        minutesInputLabel={t`Minutes`}
                        amPmInputLabel={t`AM/PM`}
                        size="xs"
                        data-testid={`${dataTestId}-time`}
                        onChange={(time) => {
                            if (time) {
                                update(current ? current.format(DATE_FORMAT) : dayjs().format(DATE_FORMAT), time);
                            }
                        }}
                    />
                </div>
            </Popover.Dropdown>
        </Popover>
    );
};
