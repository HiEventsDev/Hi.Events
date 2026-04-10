import {t} from "@lingui/macro";
import {Anchor, Tabs, Text} from "@mantine/core";
import {IconReceipt, IconUsers} from "@tabler/icons-react";
import {useState} from "react";
import {useNavigate, useParams} from "react-router";
import {AttendeeTable} from "../AttendeeTable";
import {OrdersTable} from "../OrdersTable";
import {Card} from "../Card";
import {useGetAttendees} from "../../../queries/useGetAttendees.ts";
import {useGetEventOrders} from "../../../queries/useGetEventOrders.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {IdParam, QueryFilterOperator, QueryFilters} from "../../../types.ts";
import classes from './OccurrenceAttendeesAndOrders.module.scss';

interface OccurrenceAttendeesAndOrdersProps {
    occurrenceId: IdParam;
    perPage?: number;
    onNavigateAway?: () => void;
}

export const OccurrenceAttendeesAndOrders = ({occurrenceId, perPage = 10, onNavigateAway}: OccurrenceAttendeesAndOrdersProps) => {
    const {eventId} = useParams();
    const navigate = useNavigate();
    const {data: event} = useGetEvent(eventId);
    const [activeTab, setActiveTab] = useState<string | null>('attendees');

    const filters: QueryFilters = {
        pageNumber: 1,
        perPage,
        sortBy: 'created_at',
        sortDirection: 'desc',
        filterFields: {
            event_occurrence_id: {operator: QueryFilterOperator.Equals, value: String(occurrenceId)},
        },
    };

    const attendeesQuery = useGetAttendees(eventId, filters);
    const ordersQuery = useGetEventOrders(eventId, filters);

    const attendeeCount = attendeesQuery.data?.meta?.total ?? 0;
    const orderCount = ordersQuery.data?.meta?.total ?? 0;

    const handleNavigate = (path: string) => {
        onNavigateAway?.();
        navigate(path);
    };

    const viewAllPath = activeTab === 'orders'
        ? `/manage/event/${eventId}/orders?filterFields[event_occurrence_id][eq]=${occurrenceId}`
        : `/manage/event/${eventId}/attendees?filterFields[event_occurrence_id][eq]=${occurrenceId}`;

    if (!event) return null;

    return (
        <Card className={classes.tabsCard}>
            <Tabs value={activeTab} onChange={setActiveTab}>
                <Tabs.List>
                    <Tabs.Tab value="attendees" leftSection={<IconUsers size={14}/>}>
                        {t`Recent Attendees`}
                        {attendeeCount > 0 && <span className={classes.tabCount}>{attendeeCount}</span>}
                    </Tabs.Tab>
                    <Tabs.Tab value="orders" leftSection={<IconReceipt size={14}/>}>
                        {t`Recent Orders`}
                        {orderCount > 0 && <span className={classes.tabCount}>{orderCount}</span>}
                    </Tabs.Tab>
                    <div className={classes.viewAllLink}>
                        <Anchor size="sm" onClick={() => handleNavigate(viewAllPath)}>
                            {t`View All`}
                        </Anchor>
                    </div>
                </Tabs.List>

                <Tabs.Panel value="attendees">
                    {attendeesQuery.data?.data && attendeeCount > 0 && (
                        <AttendeeTable attendees={attendeesQuery.data.data} compact occurrenceId={occurrenceId}/>
                    )}
                    {attendeesQuery.data?.data && attendeeCount === 0 && (
                        <Text c="dimmed" size="sm" py="xl" ta="center">
                            {t`No attendees yet for this date.`}
                        </Text>
                    )}
                </Tabs.Panel>

                <Tabs.Panel value="orders">
                    {ordersQuery.data?.data && orderCount > 0 && (
                        <OrdersTable event={event} orders={ordersQuery.data.data} compact/>
                    )}
                    {ordersQuery.data?.data && orderCount === 0 && (
                        <Text c="dimmed" size="sm" py="xl" ta="center">
                            {t`No orders yet for this date.`}
                        </Text>
                    )}
                </Tabs.Panel>
            </Tabs>
        </Card>
    );
};
