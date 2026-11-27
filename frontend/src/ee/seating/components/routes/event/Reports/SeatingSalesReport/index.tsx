import {ReactNode} from "react";
import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {useGetEvent} from "../../../../../../../queries/useGetEvent.ts";
import {formatCurrency} from "../../../../../../../utilites/currency.ts";
import {formatNumber} from "../../../../../../../utilites/helpers.ts";
import {ReportColumn} from "../../../../../../../components/common/ReportView";
import {EmptyCell} from "../../../../../../../components/common/TanStackTable/cells.tsx";
import {EventReport} from "../../../../../../../components/routes/event/Reports/EventReport.tsx";

interface SeatingSalesRow {
    band_key: string;
    band_name: string;
    area_id: string | null;
    area_name: string | null;
    is_band_total: boolean;
    capacity: number;
    sold: number;
    held: number;
    blocked: number;
    free: number;
    total_gross: string | null;
}

type SeatCountKey = 'capacity' | 'sold' | 'held' | 'blocked' | 'free';

const SeatingSalesReport = ({description}: { description: string }) => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);

    if (!event) {
        return null;
    }

    const emphasise = (row: SeatingSalesRow, value: ReactNode) => row.is_band_total ? <strong>{value}</strong> : value;
    const seatCount = (key: SeatCountKey, label: string): ReportColumn<SeatingSalesRow> => ({
        key,
        label,
        kind: 'count',
        sortable: false,
        render: row => emphasise(row, formatNumber(Number(row[key]))),
    });
    const areaName = (row: SeatingSalesRow) => row.is_band_total ? t`All areas` : row.area_name ?? '';

    const columns: ReportColumn<SeatingSalesRow>[] = [
        {key: 'band_name', label: t`Price band`, sortable: false, render: row => emphasise(row, row.band_name)},
        {key: 'area_name', label: t`Area`, sortable: false, render: row => emphasise(row, areaName(row)), csv: areaName},
        seatCount('capacity', t`Capacity`),
        seatCount('sold', t`Sold`),
        seatCount('held', t`In a basket`),
        seatCount('blocked', t`Held back`),
        seatCount('free', t`Free`),
        {
            key: 'total_gross',
            label: t`Gross sales`,
            kind: 'money',
            sortable: false,
            render: row => row.total_gross === null
                ? <EmptyCell/>
                : emphasise(row, formatCurrency(row.total_gross, event.currency)),
        },
    ];

    return (
        <EventReport<SeatingSalesRow>
            description={description}
            event={event}
            fileName="seating_sales_report.csv"
            columns={columns}
            hasPeriod={false}
            allTimeLabel={t`Seats as they are now`}
            totalRows={rows => rows.filter(row => row.is_band_total)}
            emptyMessage={t`Link price bands to tickets to see seat sales here.`}
            summary={sum => [
                {label: t`Capacity`, value: sum('capacity'), kind: 'count'},
                {label: t`Sold`, value: sum('sold'), kind: 'count'},
                {label: t`Free`, value: sum('free'), kind: 'count'},
                {label: t`Gross sales`, value: sum('total_gross'), kind: 'money'},
            ]}
        />
    );
};

export default SeatingSalesReport;
