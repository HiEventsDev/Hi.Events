import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {Event, EventType} from "../../../../types.ts";
import {useGetEventReport} from "../../../../queries/useGetEventReport.ts";
import {useGetAllEventOccurrences} from "../../../../queries/useGetAllEventOccurrences.ts";
import {useReportParams, useReportPeriod} from "../../../../hooks/useReportFilters.ts";
import {formatReportRange} from "../../../../utilites/reportPeriod.ts";
import {downloadBinary} from "../../../../utilites/download.ts";
import {toCsv} from "../../../../utilites/csv.ts";
import {OccurrenceSelect} from "../../../common/OccurrenceSelect";
import {ReportView, ReportViewProps} from "../../../common/ReportView";
import {ReportPeriodPicker} from "../../../common/ReportView/ReportPeriodPicker.tsx";

type EventReportProps<T> = Pick<ReportViewProps<T>, 'columns' | 'description' | 'emptyMessage' | 'summary' | 'chart' | 'emptyRows' | 'totalRows'> & {
    event: Event;
    fileName: string;
    hasPeriod?: boolean;
    hasOccurrenceFilter?: boolean;
    allTimeLabel?: string;
};

export const EventReport = <T extends object>({
    event,
    fileName,
    hasPeriod = true,
    hasOccurrenceFilter = true,
    allTimeLabel,
    columns,
    ...view
}: EventReportProps<T>) => {
    const {eventId, reportType} = useParams();
    const {param, setFilter} = useReportParams();
    const {period, range, startDate, endDate, setPeriod, setCustomRange} = useReportPeriod(event.timezone);

    const showOccurrenceFilter = hasOccurrenceFilter && event.type === EventType.RECURRING;
    const occurrences = useGetAllEventOccurrences(eventId, showOccurrenceFilter).data ?? [];
    const hasOccurrences = showOccurrenceFilter && occurrences.length > 0;
    const occurrenceParam = param('occurrence');
    const occurrenceId = showOccurrenceFilter && occurrenceParam && /^\d+$/.test(occurrenceParam)
        ? Number(occurrenceParam)
        : undefined;

    const reportQuery = useGetEventReport(
        eventId,
        reportType,
        hasPeriod ? startDate : null,
        hasPeriod ? endDate : null,
        occurrenceId,
    );
    const rows = (reportQuery.data ?? []) as T[];

    const exportCsv = (exportRows: T[]) => downloadBinary(
        new Blob([toCsv(
            columns.map(column => column.label),
            exportRows.map(row => columns.map(column => column.csv ? column.csv(row) : row[column.key] as string | number | null)),
        )], {type: 'text/csv;charset=utf-8;'}),
        fileName,
    );

    return (
        <ReportView<T>
            {...view}
            columns={columns}
            rows={rows}
            currency={event.currency}
            scope={hasPeriod ? formatReportRange(range.from, range.to) : allTimeLabel ?? t`All time`}
            isLoading={reportQuery.isLoading}
            isError={reportQuery.isError}
            isRefreshing={reportQuery.isFetching && !reportQuery.isLoading}
            onRetry={() => reportQuery.refetch()}
            onExport={exportCsv}
            filters={(hasPeriod || hasOccurrences) && (
                <>
                    {hasPeriod && (
                        <ReportPeriodPicker
                            period={period}
                            range={range}
                            timezone={event.timezone}
                            onPeriodChange={setPeriod}
                            onCustomRangeChange={setCustomRange}
                        />
                    )}
                    {hasOccurrences && (
                        <OccurrenceSelect
                            occurrences={occurrences}
                            timezone={event.timezone}
                            value={occurrenceId ? String(occurrenceId) : null}
                            onChange={value => setFilter('occurrence', value)}
                            placeholder={t`All Occurrences`}
                            clearable
                            style={{width: 230}}
                        />
                    )}
                </>
            )}
        />
    );
};
