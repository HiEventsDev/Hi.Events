import {t} from "@lingui/macro";
import {SegmentedControl} from "@mantine/core";
import {useLocation, useNavigate} from "react-router";
import classes from "./EventsStatusControl.module.scss";

interface EventsStatusControlProps {
    baseUrl: string;
    eventsState?: string;
}

export const EventsStatusControl = ({baseUrl, eventsState}: EventsStatusControlProps) => {
    const navigate = useNavigate();
    const location = useLocation();

    const showEventsState = (state: string) => {
        const params = new URLSearchParams(location.search);
        params.delete('pageNumber');
        const search = params.toString();

        navigate({pathname: `${baseUrl}/${state}`, search: search ? `?${search}` : ''});
    };

    return (
        <SegmentedControl
            className={classes.control}
            size="sm"
            aria-label={t`Filter by status`}
            value={eventsState ?? 'upcoming'}
            onChange={showEventsState}
            data={[
                {value: 'upcoming', label: t`Upcoming`},
                {value: 'ended', label: t`Ended`},
                {value: 'archived', label: t`Archived`},
            ]}
        />
    );
};
