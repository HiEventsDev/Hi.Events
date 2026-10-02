import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {useGetEvent} from "../../../../../../../queries/useGetEvent.ts";
import {formatCurrency} from "../../../../../../../utilites/currency.ts";
import ReportTable from "../../../../../../../components/common/ReportTable";

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

const SeatingSalesReport = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const event = eventQuery.data;

    if (!event) {
        return null;
    }

    const emphasise = (row: SeatingSalesRow, value: string | number) => row.is_band_total ? <strong>{value}</strong> : value;

    const columns = [
        {
            key: 'band_name' as const,
            label: t`Price band`,
            render: (value: string, row: SeatingSalesRow) => emphasise(row, value),
        },
        {
            key: 'area_name' as const,
            label: t`Area`,
            render: (value: string | null, row: SeatingSalesRow) => row.is_band_total ? <strong>{t`All areas`}</strong> : value,
        },
        {
            key: 'capacity' as const,
            label: t`Capacity`,
            render: (value: number, row: SeatingSalesRow) => emphasise(row, value),
        },
        {
            key: 'sold' as const,
            label: t`Sold`,
            render: (value: number, row: SeatingSalesRow) => emphasise(row, value),
        },
        {
            key: 'held' as const,
            label: t`In a basket`,
            render: (value: number, row: SeatingSalesRow) => emphasise(row, value),
        },
        {
            key: 'blocked' as const,
            label: t`Held back`,
            render: (value: number, row: SeatingSalesRow) => emphasise(row, value),
        },
        {
            key: 'free' as const,
            label: t`Free`,
            render: (value: number, row: SeatingSalesRow) => emphasise(row, value),
        },
        {
            key: 'total_gross' as const,
            label: t`Gross Sales`,
            render: (value: string | null, row: SeatingSalesRow) => value === null ? '—' : emphasise(row, formatCurrency(value, event.currency)),
        },
    ];

    return (
        <ReportTable<SeatingSalesRow>
            title={t`Seating Sales`}
            columns={columns}
            isLoading={eventQuery.isLoading}
            downloadFileName="seating_sales_report.csv"
            event={event}
            showDateFilter={false}
        />
    );
};

export default SeatingSalesReport;
