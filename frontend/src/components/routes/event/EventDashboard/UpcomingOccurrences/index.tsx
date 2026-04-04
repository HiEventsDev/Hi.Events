import {t} from "@lingui/macro";
import {Anchor, Skeleton, Text} from "@mantine/core";
import {useNavigate} from "react-router";
import {Card} from "../../../../common/Card";
import {useGetEventOccurrences} from "../../../../../queries/useGetEventOccurrences.ts";
import {Event, IdParam} from "../../../../../types.ts";
import {CalendarView} from "../../OccurrencesTab/CalendarView";
import classes from "./UpcomingOccurrences.module.scss";

interface UpcomingOccurrencesProps {
    eventId: IdParam;
    event: Event;
}

export const UpcomingOccurrences = ({eventId, event}: UpcomingOccurrencesProps) => {
    const navigate = useNavigate();
    const {data: occurrencesData, isLoading} = useGetEventOccurrences(eventId, {
        pageNumber: 1,
        perPage: 500,
        sortBy: 'start_date',
        sortDirection: 'asc',
    });

    const occurrences = occurrencesData?.data || [];

    if (isLoading) {
        return (
            <Card className={classes.upcomingCard}>
                <Skeleton height={300} radius="md"/>
            </Card>
        );
    }

    return (
        <Card className={classes.upcomingCard}>
            <div className={classes.header}>
                <h2>{t`Schedule`}</h2>
                <Anchor
                    size="sm"
                    onClick={() => navigate(`/manage/event/${eventId}/occurrences`)}
                >
                    {t`Manage Schedule`}
                </Anchor>
            </div>

            {occurrences.length === 0 ? (
                <div className={classes.emptyState}>
                    <Text size="sm" c="dimmed">
                        {t`No dates scheduled.`}
                    </Text>
                </div>
            ) : (
                <CalendarView
                    occurrences={occurrences}
                    eventTimezone={event.timezone}
                    onOccurrenceClick={(id) => navigate(`/manage/event/${eventId}/occurrences/${id}`)}
                />
            )}
        </Card>
    );
};
