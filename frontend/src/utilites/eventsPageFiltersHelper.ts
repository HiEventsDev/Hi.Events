import {EventStatus, QueryFilterOperator, QueryFilters} from "../types.ts";
import {useParams} from "react-router-dom";

export const getEventQueryFilters = (searchParams: Partial<QueryFilters>) => {
    const {eventsState, organizerId} = useParams();
    let filter = {};
    if (eventsState === 'upcoming' || !eventsState) {
        filter = {
            additionalParams: {
                eventsStatus: 'upcoming',
            },
            filterFields: {}
        };
    } else if (eventsState === 'ended') {
        filter = {
            filterFields: {
                end_date: {operator: QueryFilterOperator.LessThanOrEquals, value: 'now'},
                status: {operator: QueryFilterOperator.NotEquals, value: EventStatus.ARCHIVED},
            }
        };
    } else if (eventsState === 'archived') {
        filter = {
            filterFields: {
                status: {operator: QueryFilterOperator.Equals, value: EventStatus.ARCHIVED},
            }
        };
    }

    if (organizerId) {
        // add the organizer filter on top of the other filters
        filter = {
            ...filter,
            filterFields: {
                organizer_id: {operator: QueryFilterOperator.Equals, value: organizerId},
                ...filter.filterFields
            }
        }
    }

    return {
        ...searchParams,
        ...filter,
    };
}
