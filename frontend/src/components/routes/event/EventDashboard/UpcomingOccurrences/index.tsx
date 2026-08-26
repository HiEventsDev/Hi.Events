import {useMemo, useState} from "react";
import {t} from "@lingui/macro";
import {Anchor, Skeleton} from "@mantine/core";
import {useNavigate} from "react-router";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {Card} from "../../../../common/Card";
import {useGetEventOccurrences} from "../../../../../queries/useGetEventOccurrences.ts";
import {Event, IdParam, QueryFilterOperator, QueryFilters} from "../../../../../types.ts";
import {CalendarView} from "../../OccurrencesTab/CalendarView";
import classes from "./UpcomingOccurrences.module.scss";

dayjs.extend(utc);
dayjs.extend(timezone);

interface UpcomingOccurrencesProps {
    eventId: IdParam;
    event: Event;
}

export const UpcomingOccurrences = ({eventId, event}: UpcomingOccurrencesProps) => {
    const navigate = useNavigate();
    const [currentMonth, setCurrentMonth] = useState<dayjs.Dayjs>(() => dayjs().startOf('month'));

    const calendarRange = useMemo(() => {
        if (!event.timezone) return null;
        const tz = event.timezone;
        const monthStart = dayjs.tz(currentMonth.format('YYYY-MM-01'), tz).startOf('month');
        const startDay = monthStart.day();
        const offset = startDay === 0 ? 6 : startDay - 1;
        const gridStart = monthStart.subtract(offset, 'day').startOf('day');
        const gridEnd = gridStart.add(42, 'day').endOf('day');
        return {
            start: gridStart.utc().format('YYYY-MM-DD HH:mm:ss'),
            end: gridEnd.utc().format('YYYY-MM-DD HH:mm:ss'),
        };
    }, [currentMonth, event.timezone]);

    const queryParams: QueryFilters = useMemo(() => {
        if (!calendarRange) return {pageNumber: 1, perPage: 500, filterFields: {}};
        return {
            pageNumber: 1,
            perPage: 500,
            filterFields: {
                start_date: [
                    {operator: QueryFilterOperator.GreaterThanOrEquals, value: calendarRange.start},
                    {operator: QueryFilterOperator.LessThanOrEquals, value: calendarRange.end},
                ],
            },
        };
    }, [calendarRange]);

    const {data: occurrencesData, isLoading} = useGetEventOccurrences(
        eventId,
        queryParams,
        calendarRange !== null,
    );

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

            <CalendarView
                occurrences={occurrences}
                eventTimezone={event.timezone}
                currentMonth={currentMonth}
                onMonthChange={setCurrentMonth}
                onOccurrenceClick={(id) => navigate(`/manage/event/${eventId}/occurrences/${id}`)}
            />
        </Card>
    );
};
