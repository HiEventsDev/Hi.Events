import {t} from "@lingui/macro";
import {Group, Radio, Text} from "@mantine/core";
import {AttendeeDetailsCollectionMethod} from "../../../../types.ts";
import classes from "./QuestionsBuilder.module.scss";

interface AttendeeDetailsSettingProps {
    value: AttendeeDetailsCollectionMethod;
    disabled: boolean;
    hasAttendeeQuestions: boolean;
    onChange: (value: AttendeeDetailsCollectionMethod) => void;
}

export const AttendeeDetailsSetting = ({value, disabled, hasAttendeeQuestions, onChange}: AttendeeDetailsSettingProps) => {
    const options: { value: AttendeeDetailsCollectionMethod, label: string, description: string }[] = [
        {
            value: 'PER_TICKET',
            label: t`Ask for each attendee's name and email`,
            description: t`Each ticket is issued to its own attendee, so it reaches the right person and can be checked in individually.`,
        },
        {
            value: 'PER_ORDER',
            label: t`Use the buyer's name and email for every ticket`,
            description: t`Quicker checkout, but every ticket is issued in the buyer's name.`,
        },
    ];

    return (
        <div className={classes.attendeeDetails}>
            <Radio.Group
                value={value}
                onChange={(next) => onChange(next as AttendeeDetailsCollectionMethod)}
                label={t`Attendee name and email`}
            >
                <div className={classes.attendeeDetailsOptions}>
                    {options.map((option) => (
                        <Radio.Card
                            key={option.value}
                            value={option.value}
                            disabled={disabled}
                            className={classes.attendeeDetailsOption}
                        >
                            <Group wrap="nowrap" align="flex-start" gap="sm">
                                <Radio.Indicator mt={2}/>
                                <div>
                                    <Text size="sm" fw={500}>{option.label}</Text>
                                    <Text size="xs" c="dimmed">{option.description}</Text>
                                </div>
                            </Group>
                        </Radio.Card>
                    ))}
                </div>
            </Radio.Group>
            {value === 'PER_ORDER' && hasAttendeeQuestions && (
                <Text size="xs" c="dimmed" mt={6}>
                    {t`The questions below are still asked once for every ticket.`}
                </Text>
            )}
        </div>
    );
};
