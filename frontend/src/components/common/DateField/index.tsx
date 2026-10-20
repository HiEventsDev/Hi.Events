import {useId} from "react";
import {DatePickerInput, DatePickerInputProps} from "@mantine/dates";
import {t} from "@lingui/macro";
import {IconCalendar} from "@tabler/icons-react";
import {getDatePickerFormat} from "../../../utilites/dates.ts";
import classes from "./DateField.module.scss";

interface DateFieldProps extends Omit<DatePickerInputProps, 'value' | 'onChange'> {
    value?: string | null;
    onChange?: (value: string) => void;
}

export const DateField = ({id, value, onChange, required, ...props}: DateFieldProps) => {
    const generatedId = useId();
    const inputId = id ?? generatedId;

    return (
        <DatePickerInput
            placeholder={t`Select a date`}
            leftSection={<IconCalendar size={16}/>}
            leftSectionPointerEvents="none"
            aria-labelledby={props['aria-label'] ? undefined : `${inputId}-label ${inputId}`}
            clearButtonProps={{'aria-label': t`Clear`}}
            popoverProps={{returnFocus: true}}
            {...props}
            id={inputId}
            classNames={{root: classes.root, input: classes.input}}
            required={required}
            clearable={!required}
            value={value || null}
            onChange={(next) => onChange?.(next ?? '')}
            valueFormat={getDatePickerFormat()}
            withNativeLevelSelect
        />
    );
};
