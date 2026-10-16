import {ReactNode} from "react";
import {DatesProvider, DayOfWeek, useDatesContext} from "@mantine/dates";

interface OrganizerDatesProviderProps {
    firstDayOfWeek?: DayOfWeek;
    children: ReactNode;
}

export const OrganizerDatesProvider = ({firstDayOfWeek, children}: OrganizerDatesProviderProps) => {
    const {locale, weekendDays, labelSeparator, consistentWeeks, firstDayOfWeek: inherited} = useDatesContext();

    return (
        <DatesProvider settings={{
            locale,
            weekendDays,
            labelSeparator,
            consistentWeeks,
            firstDayOfWeek: firstDayOfWeek ?? inherited,
        }}>
            {children}
        </DatesProvider>
    );
};
