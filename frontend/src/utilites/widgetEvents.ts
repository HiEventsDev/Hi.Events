import {QueryFilterOperator, QueryFilters} from "../types.ts";
import {WidgetSettings} from "./widgetConfig.ts";

const DEFAULT_LIMIT = 12;

export const organizerWidgetEventsFilters = (config: WidgetSettings, status: 'upcoming' | 'ended' = 'upcoming', pageNumber = 1): QueryFilters => ({
    pageNumber,
    perPage: config.limit && config.limit > 0 ? Math.min(config.limit, 50) : DEFAULT_LIMIT,
    sortBy: 'start_date',
    sortDirection: status === 'upcoming' ? 'asc' : 'desc',
    additionalParams: {eventsStatus: status},
    filterFields: config.event_category
        ? {category: {operator: QueryFilterOperator.Equals, value: config.event_category}}
        : {},
});
