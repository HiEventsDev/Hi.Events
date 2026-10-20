import {ReactNode, useId, useState} from "react";
import {Group, Input} from "@mantine/core";
import {TimePicker} from "@mantine/dates";
import {t} from "@lingui/macro";
import {DateField} from "../DateField";
import {joinDateAndTime} from "./dateTimeValue.ts";
import {getTimePickerFormat} from "../../../utilites/dates.ts";
import classes from "./DateTimeField.module.scss";

interface DateTimeFieldProps {
    value?: string | null;
    onChange?: (value: string) => void;
    onFocus?: () => void;
    onBlur?: () => void;
    label?: ReactNode;
    description?: ReactNode;
    error?: ReactNode;
    required?: boolean;
    disabled?: boolean;
    minDate?: string;
    defaultTime?: string;
}

export const DateTimeField = ({value, onChange, onFocus, onBlur, label, description, error, required, disabled, minDate, defaultTime = '00:00'}: DateTimeFieldProps) => {
    const id = useId();
    const [pendingTime, setPendingTime] = useState('');
    const date = value ? value.slice(0, 10) : '';
    const time = value ? value.slice(11, 16) : pendingTime;
    const [pickerTime, setPickerTime] = useState(time);
    const [pickerKey, setPickerKey] = useState(0);
    const describedByLabel = {'aria-describedby': `${id}-label`};

    if (time !== pickerTime) {
        setPickerTime(time);
        setPickerKey((key) => key + 1);
    }

    const handleDateChange = (nextDate: string) => {
        onChange?.(joinDateAndTime(nextDate, time, defaultTime));
    };

    const handleTimeChange = (nextTime: string) => {
        if (!nextTime) {
            return;
        }
        const hhmm = nextTime.slice(0, 5);
        setPickerTime(hhmm);
        if (date) {
            onChange?.(`${date}T${hhmm}`);
        } else {
            setPendingTime(hhmm);
        }
    };

    return (
        <Input.Wrapper
            id={id}
            label={label}
            description={description}
            error={error}
            withAsterisk={required}
        >
            <Group gap="xs" wrap="wrap" className={classes.row}>
                <DateField
                    id={id}
                    className={classes.date}
                    value={date}
                    onChange={handleDateChange}
                    onFocus={onFocus}
                    onBlur={onBlur}
                    required={required}
                    disabled={disabled}
                    error={!!error}
                    minDate={minDate?.slice(0, 10)}
                />
                <TimePicker
                    key={pickerKey}
                    className={classes.time}
                    value={time}
                    onChange={handleTimeChange}
                    disabled={disabled}
                    error={!!error}
                    format={getTimePickerFormat()}
                    withDropdown
                    hoursInputLabel={t`Hours`}
                    minutesInputLabel={t`Minutes`}
                    amPmInputLabel={t`AM/PM`}
                    hoursInputProps={describedByLabel}
                    minutesInputProps={describedByLabel}
                    amPmSelectProps={describedByLabel}
                />
            </Group>
        </Input.Wrapper>
    );
};
