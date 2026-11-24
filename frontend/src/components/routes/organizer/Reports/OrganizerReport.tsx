import {ReactNode} from "react";
import {useParams} from "react-router";
import {Select} from "@mantine/core";
import {t} from "@lingui/macro";
import dayjs from "dayjs";
import {Organizer} from "../../../../types.ts";
import {organizerClient} from "../../../../api/organizer.client.ts";
import {useGetOrganizerReport} from "../../../../queries/useGetOrganizerReport.ts";
import {useGetOrganizerStats} from "../../../../queries/useGetOrganizerStats.ts";
import {useReportParams, useReportPeriod} from "../../../../hooks/useReportFilters.ts";
import {downloadBinary} from "../../../../utilites/download.ts";
import {ReportView, ReportViewProps} from "../../../common/ReportView";
import {ReportPeriodPicker} from "../../../common/ReportView/ReportPeriodPicker.tsx";
import {formatReportRange} from "../../../../utilites/reportPeriod.ts";
import classes from "../../../common/ReportView/ReportView.module.scss";

const ROWS_PER_PAGE = 1000;
const EXPORT_TIMESTAMP_FORMAT = 'YYYY-MM-DD_HH-mm-ss';

type CurrencyFilter = 'none' | 'optional' | 'required';

type OrganizerReportProps<T> = Pick<ReportViewProps<T>, 'columns' | 'description' | 'emptyMessage' | 'summary' | 'chart' | 'emptyRows' | 'rowCurrency' | 'notice'> & {
    organizer: Organizer;
    hasPeriod?: boolean;
    currencyFilter?: CurrencyFilter;
    eventId?: number | null;
    extraFilters?: ReactNode;
};

export const OrganizerReport = <T extends object>({
    organizer,
    hasPeriod = true,
    currencyFilter = 'optional',
    eventId = null,
    extraFilters,
    ...view
}: OrganizerReportProps<T>) => {
    const {organizerId, reportType} = useParams();
    const timezone = organizer.timezone || 'UTC';
    const {param, setParams, setFilter} = useReportParams();
    const {period, range, startDate, endDate, setPeriod, setCustomRange} = useReportPeriod(timezone);

    const statsQuery = useGetOrganizerStats(organizerId, organizer.currency);
    const currencies = currencyFilter === 'none' ? [] : statsQuery.data?.all_organizers_currencies ?? [];
    const currencyParam = param('currency')?.toUpperCase() ?? null;
    const selectedCurrency = currencyParam && currencies.includes(currencyParam) ? currencyParam : null;
    const currency = currencyFilter === 'required' ? selectedCurrency ?? organizer.currency ?? null : selectedCurrency;
    const page = Math.max(1, Number(param('page')) || 1);

    const reportQuery = useGetOrganizerReport(
        organizerId,
        reportType ?? '',
        hasPeriod ? startDate : null,
        hasPeriod ? endDate : null,
        currency,
        eventId,
        page,
        ROWS_PER_PAGE,
    );
    const rows = (reportQuery.data?.data ?? []) as T[];
    const pagination = reportQuery.data?.pagination;

    const exportCsv = async () => {
        const csv = await organizerClient.exportOrganizerReport(
            organizerId,
            reportType ?? '',
            hasPeriod ? startDate.toISOString() : null,
            hasPeriod ? endDate.toISOString() : null,
            currency,
            eventId,
        );
        downloadBinary(csv, `${reportType}_${dayjs().format(EXPORT_TIMESTAMP_FORMAT)}.csv`);
    };

    const currencyOptions = currencyFilter === 'required'
        ? currencies
        : [{value: '', label: t`All currencies`}, ...currencies.map(code => ({value: code, label: code}))];

    return (
        <ReportView<T>
            {...view}
            rows={rows}
            currency={currency ?? organizer.currency ?? 'USD'}
            scope={hasPeriod ? formatReportRange(range.from, range.to) : t`All time`}
            isLoading={reportQuery.isLoading}
            isError={reportQuery.isError}
            isRefreshing={reportQuery.isFetching && !reportQuery.isLoading}
            onRetry={() => reportQuery.refetch()}
            onExport={exportCsv}
            pagination={pagination && {
                page,
                lastPage: pagination.last_page,
                total: pagination.total,
                onChange: next => setParams({page: next === 1 ? null : String(next)}),
            }}
            filters={(hasPeriod || currencies.length > 1 || !!extraFilters) && (
                <>
                    {hasPeriod && (
                        <ReportPeriodPicker
                            period={period}
                            range={range}
                            timezone={timezone}
                            onPeriodChange={setPeriod}
                            onCustomRangeChange={setCustomRange}
                        />
                    )}
                    {currencies.length > 1 && (
                        <Select
                            className={classes.currencySelect}
                            aria-label={t`Currency`}
                            allowDeselect={false}
                            data={currencyOptions}
                            value={currencyFilter === 'required' ? currency : selectedCurrency ?? ''}
                            onChange={value => setFilter('currency', value || null)}
                        />
                    )}
                    {extraFilters}
                </>
            )}
        />
    );
};
