import {ReactNode} from "react";
import {DatesProvider, DayOfWeek, useDatesContext} from "@mantine/dates";

interface OrganizerDatesProviderProps {
    firstDayOfWeek?: DayOfWeek;
    children: ReactNode;
}

export const OrganizerDatesProvider = ({firstDayOfWeek, children}: OrganizerDatesProviderProps) => {
    const inherited = useDatesContext();

    return (
        <DatesProvider settings={{
            locale: inherited.locale,
            weekendDays: inherited.weekendDays,
            labelSeparator: inherited.labelSeparator,
            consistentWeeks: inherited.consistentWeeks,
            firstDayOfWeek: firstDayOfWeek ?? inherited.firstDayOfWeek,
        }}>
            {children}
        </DatesProvider>
    );
};
