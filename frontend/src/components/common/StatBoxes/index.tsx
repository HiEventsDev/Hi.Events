import {useMemo} from "react";
import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {KpiCell, KpiGrid} from "../KpiGrid";
import {PeriodPreset} from "../PeriodSelector";
import {computeDelta, periodPresetToDateRange, previousPeriodRange} from "../../../utilites/periodPreset.ts";
import {useGetEventStats} from "../../../queries/useGetEventStats.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {formatNumber} from "../../../utilites/helpers.ts";
import {EventStats, IdParam} from "../../../types.ts";

export const StatBox = KpiCell;

interface StatBoxesProps {
    occurrenceId?: IdParam;
    dateRange?: PeriodPreset;
}

const sparkFrom = (stats: EventStats | undefined, key: keyof EventStats['daily_stats'][number]): number[] => {
    if (!stats?.daily_stats) return [];
    return stats.daily_stats.map((d) => Number(d[key] ?? 0));
};

export const StatBoxes = ({occurrenceId, dateRange}: StatBoxesProps = {}) => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const currency = eventQuery?.data?.currency;

    const currentRange = useMemo(
        () => periodPresetToDateRange(dateRange ?? 'last_30_days'),
        [dateRange],
    );
    const previousRange = useMemo(() => previousPeriodRange(currentRange), [currentRange]);

    const currentStatsQuery = useGetEventStats(eventId, {
        occurrenceId,
        startDate: currentRange.startDate,
        endDate: currentRange.endDate,
    });
    const previousStatsQuery = useGetEventStats(eventId, {
        occurrenceId,
        startDate: previousRange.startDate,
        endDate: previousRange.endDate,
    });

    const isLoading = currentStatsQuery.isLoading || previousStatsQuery.isLoading;
    const current = currentStatsQuery.data;
    const previous = previousStatsQuery.data;

    const cells = [
        {
            key: 'attendees',
            label: t`Attendees`,
            value: formatNumber(current?.total_attendees_registered ?? 0),
            sparkline: sparkFrom(current, 'attendees_registered'),
            delta: computeDelta(current?.total_attendees_registered, previous?.total_attendees_registered),
        },
        {
            key: 'products_sold',
            label: t`Products sold`,
            value: formatNumber(current?.total_products_sold ?? 0),
            sparkline: sparkFrom(current, 'products_sold'),
            delta: computeDelta(current?.total_products_sold, previous?.total_products_sold),
        },
        {
            key: 'refunded',
            label: t`Refunded`,
            value: formatCurrency(current?.total_refunded ?? 0, currency),
            sparkline: sparkFrom(current, 'total_refunded'),
            delta: computeDelta(current?.total_refunded, previous?.total_refunded),
        },
        {
            key: 'gross_sales',
            label: t`Gross sales`,
            value: formatCurrency(current?.total_gross_sales ?? 0, currency),
            sparkline: sparkFrom(current, 'total_sales_gross'),
            delta: computeDelta(current?.total_gross_sales, previous?.total_gross_sales),
        },
        {
            key: 'page_views',
            label: t`Page views`,
            value: formatNumber(current?.total_views ?? 0),
            sparkline: undefined as number[] | undefined,
            delta: computeDelta(current?.total_views, previous?.total_views),
        },
        {
            key: 'orders',
            label: t`Completed orders`,
            value: formatNumber(current?.total_orders ?? 0),
            sparkline: sparkFrom(current, 'orders_created'),
            delta: computeDelta(current?.total_orders, previous?.total_orders),
        },
    ];

    const visibleCells = occurrenceId ? cells.filter((cell) => cell.key !== 'page_views') : cells;

    return (
        <KpiGrid>
            {visibleCells.map((cell) => (
                <KpiCell
                    key={cell.key}
                    label={cell.label}
                    value={cell.value}
                    sparkline={cell.sparkline}
                    delta={cell.delta}
                    isLoading={isLoading}
                />
            ))}
        </KpiGrid>
    );
};
