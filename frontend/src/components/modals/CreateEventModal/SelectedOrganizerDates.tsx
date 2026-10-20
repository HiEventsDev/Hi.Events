import {ReactNode} from "react";
import {IdParam} from "../../../types.ts";
import {useGetOrganizerSettings} from "../../../queries/useGetOrganizerSettings.ts";
import {OrganizerDatesProvider} from "../../common/OrganizerDatesProvider";

interface LoadedOrganizerDatesProps {
    organizerId: IdParam;
    children: ReactNode;
}

const LoadedOrganizerDates = ({organizerId, children}: LoadedOrganizerDatesProps) => {
    const {data: settings} = useGetOrganizerSettings(organizerId);

    return (
        <OrganizerDatesProvider firstDayOfWeek={settings?.first_day_of_week}>
            {children}
        </OrganizerDatesProvider>
    );
};

interface SelectedOrganizerDatesProps {
    organizerId: IdParam | null;
    children: ReactNode;
}

export const SelectedOrganizerDates = ({organizerId, children}: SelectedOrganizerDatesProps) => (
    organizerId
        ? <LoadedOrganizerDates organizerId={organizerId}>{children}</LoadedOrganizerDates>
        : <>{children}</>
);
