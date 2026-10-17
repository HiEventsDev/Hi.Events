import {DatePickerInput, DatePickerInputProps} from "@mantine/dates";
import {t} from "@lingui/macro";
import {IconCalendar} from "@tabler/icons-react";
import {getDatePickerFormat} from "../../../utilites/dates.ts";

interface DateFieldProps extends Omit<DatePickerInputProps, 'value' | 'onChange'> {
    value?: string | null;
    onChange?: (value: string) => void;
}

export const DateField = ({value, onChange, required, ...props}: DateFieldProps) => (
    <DatePickerInput
        placeholder={t`Select a date`}
        leftSection={<IconCalendar size={16}/>}
        {...props}
        required={required}
        clearable={!required}
        value={value || null}
        onChange={(next) => onChange?.(next ?? '')}
        valueFormat={getDatePickerFormat()}
        withNativeLevelSelect
    />
);
