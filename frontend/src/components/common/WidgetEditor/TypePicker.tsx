import {SegmentedControl, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {WidgetType} from "../../../utilites/widgetConfig.ts";
import classes from './WidgetEditor.module.scss';

const typeOptions = (): { value: WidgetType; label: string; description: string }[] => [
    {value: 'TICKETS', label: t`Tickets`, description: t`Visitors pick tickets right on your page.`},
    {value: 'BUTTON', label: t`Button`, description: t`A button that opens tickets in a pop-up.`},
    {value: 'LINEUP', label: t`Lineup`, description: t`The people on your bill, grouped by billing.`},
    {value: 'SCHEDULE', label: t`Schedule`, description: t`Your timetable by day, with stage filters.`},
];

export const TypePicker = ({value, onChange, disabled}: { value: WidgetType; onChange: (type: WidgetType) => void; disabled: boolean }) => {
    const options = typeOptions();

    return (
        <div className={classes.typePicker}>
            <SegmentedControl
                fullWidth
                size="sm"
                disabled={disabled}
                value={value}
                onChange={(next) => onChange(next as WidgetType)}
                aria-label={t`Widget type`}
                data={options.map(option => ({
                    value: option.value,
                    label: <span data-testid={`widget-type-${option.value.toLowerCase()}`}>{option.label}</span>,
                }))}
            />
            <Text size="xs" c="dimmed" className={classes.typeDescription}>
                {options.find(option => option.value === value)?.description}
            </Text>
        </div>
    );
};
