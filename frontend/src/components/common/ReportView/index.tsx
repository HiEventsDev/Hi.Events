import {ReactNode, useState} from "react";
import {ActionIcon, Button, Pagination, Skeleton, Switch} from "@mantine/core";
import {BarChart} from "@mantine/charts";
import {IconDownload, IconInfoCircle} from "@tabler/icons-react";
import {plural, t} from "@lingui/macro";
import {TableCardSkeleton} from "../TableCard";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {CellText, EmptyCell} from "../TanStackTable/cells.tsx";
import {SortHeader} from "../TanStackTable/sorting.tsx";
import {NoResultsSplash} from "../NoResultsSplash";
import {useReportParams} from "../../../hooks/useReportFilters.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {getClientLocale, toIntlLocale} from "../../../locales.ts";
import {formatNumber} from "../../../utilites/helpers.ts";
import {showError} from "../../../utilites/notifications.tsx";
import {sharedCurrency, SortDirection, sortRows, sumColumn} from "../../../utilites/reportTotals.ts";
import {CsvCell} from "../../../utilites/csv.ts";
import classes from "./ReportView.module.scss";

export type ReportValueKind = 'text' | 'count' | 'money';

export interface ReportColumn<T> {
    key: keyof T & string;
    label: string;
    kind?: ReportValueKind;
    render?: (row: T) => ReactNode;
    total?: boolean;
    sortable?: boolean;
    csv?: (row: T) => CsvCell;
}

export interface ReportSummaryItem {
    label: string;
    value: number | string;
    kind: ReportValueKind;
}

export interface ReportChart<T> {
    dateKey: keyof T & string;
    valueKey: keyof T & string;
    label: string;
    kind: 'count' | 'money';
    formatDate: (value: string) => string;
}

export interface ReportEmptyRows<T> {
    isEmpty: (row: T) => boolean;
    toggleLabel: string;
    allHiddenMessage: string;
}

export interface ReportPagination {
    page: number;
    lastPage: number;
    total: number;
    onChange: (page: number) => void;
}

export interface ReportViewProps<T> {
    columns: ReportColumn<T>[];
    rows: T[];
    currency: string;
    description: string;
    scope: string;
    emptyMessage: string;
    isLoading: boolean;
    isError: boolean;
    isRefreshing: boolean;
    onRetry: () => void;
    onExport: (rows: T[]) => void | Promise<void>;
    filters?: ReactNode;
    notice?: ReactNode;
    rowCurrency?: (row: T) => string;
    totalRows?: (rows: T[]) => T[];
    summary?: (sum: (key: keyof T & string) => number, rows: T[]) => ReportSummaryItem[];
    chart?: ReportChart<T>;
    emptyRows?: ReportEmptyRows<T>;
    pagination?: ReportPagination;
}

const isBlank = (value: unknown) => value === null || value === undefined || value === '';

export const ReportView = <T extends object>({
    columns,
    rows,
    currency,
    description,
    scope,
    emptyMessage,
    isLoading,
    isError,
    isRefreshing,
    onRetry,
    onExport,
    filters,
    notice,
    rowCurrency,
    totalRows,
    summary,
    chart,
    emptyRows,
    pagination,
}: ReportViewProps<T>) => {
    const {param, setParams} = useReportParams();
    const [isExporting, setIsExporting] = useState(false);

    const isPaged = (pagination?.lastPage ?? 1) > 1;
    const sortKey = isPaged ? null : columns.find(column => column.key === param('sort'))?.key ?? null;
    const sortDirection: SortDirection = param('dir') === 'asc' ? 'asc' : 'desc';
    const showEmptyRows = param('empty') === 'show';

    const totalsCurrency = rowCurrency ? sharedCurrency(rows, rowCurrency) : currency;
    const hasMixedCurrencies = rows.length > 0 && !totalsCurrency && columns.some(column => column.kind === 'money');
    const totalsSource = totalRows ? totalRows(rows) : rows;
    const sum = (key: keyof T & string) => sumColumn(totalsSource, key);

    const sortedRows = sortRows(rows, sortKey, sortDirection);
    const visibleRows = emptyRows && !showEmptyRows ? sortedRows.filter(row => !emptyRows.isEmpty(row)) : sortedRows;

    const formatValue = (kind: ReportValueKind, value: number | string, valueCurrency: string): string => {
        if (kind === 'money') {
            return formatCurrency(value, valueCurrency);
        }
        return kind === 'count' ? String(formatNumber(Number(value))) : String(value);
    };

    const renderCell = (column: ReportColumn<T>, row: T): ReactNode => {
        if (column.render) {
            return column.render(row);
        }

        const value = row[column.key] as unknown;
        if (isBlank(value)) {
            return <EmptyCell/>;
        }

        const kind = column.kind ?? 'text';
        if (kind === 'text') {
            return <CellText>{String(value)}</CellText>;
        }

        return (
            <span className={Number(value) === 0 ? classes.zero : undefined}>
                {formatValue(kind, value as number | string, rowCurrency?.(row) ?? currency)}
            </span>
        );
    };

    const renderTotal = (column: ReportColumn<T>, index: number): ReactNode => {
        if (index === 0) {
            return t`Total`;
        }

        const kind = column.kind ?? 'text';
        if (kind === 'text' || column.total === false || (kind === 'money' && !totalsCurrency)) {
            return null;
        }

        return formatValue(kind, sum(column.key), totalsCurrency ?? currency);
    };

    const tableColumns: TanStackTableColumn<T>[] = columns.map((column, index) => ({
        id: column.key,
        header: column.sortable === false || isPaged
            ? column.label
            : () => (
                <SortHeader
                    label={column.label}
                    field={column.key}
                    sort={{
                        sortBy: sortKey ?? '',
                        sortDirection,
                        onSort: (sortBy, direction) => setParams({sort: sortBy, dir: direction}),
                    }}
                />
            ),
        cell: ({row}) => renderCell(column, row.original),
        footer: () => renderTotal(column, index),
        meta: {
            label: column.label,
            sticky: index === 0 ? 'left' : undefined,
            align: column.kind === 'money' || column.kind === 'count' ? 'right' : undefined,
        },
    }));

    const summaryItems = (summary?.(sum, totalsSource) ?? [])
        .filter(item => item.kind !== 'money' || !!totalsCurrency || rows.length === 0);
    const showSummary = !!summary && !isPaged && !isError && (isLoading || summaryItems.length > 0);
    const showChart = !!chart && !isLoading && rows.length > 1
        && (chart.kind === 'count' || !!totalsCurrency)
        && rows.some(row => Number(row[chart.valueKey]) !== 0);
    const showTotals = !isPaged && visibleRows.length > 1;

    const chartData = chart && showChart
        ? sortRows(rows, chart.dateKey, 'asc').map(row => ({
            date: chart.formatDate(String(row[chart.dateKey])),
            value: Number(row[chart.valueKey]) || 0,
        }))
        : [];

    const formatAxisTick = (value: number): string => chart?.kind === 'money'
        ? new Intl.NumberFormat(toIntlLocale(getClientLocale()), {
            style: 'currency',
            currency: totalsCurrency ?? currency,
            notation: 'compact',
            maximumFractionDigits: 1,
        }).format(value)
        : String(formatNumber(value));

    const handleExport = async () => {
        setIsExporting(true);
        try {
            await onExport(sortedRows);
        } catch {
            showError(t`Couldn't export this report. Try again.`);
        } finally {
            setIsExporting(false);
        }
    };

    const rowCount = () => {
        const shown = formatNumber(visibleRows.length);
        const total = formatNumber(pagination && isPaged ? pagination.total : rows.length);

        if (pagination && isPaged) {
            return t`Showing ${shown} of ${total} rows`;
        }
        if (visibleRows.length < rows.length) {
            return t`${shown} of ${total} rows`;
        }
        return plural(rows.length, {one: '# row', other: '# rows'});
    };

    const renderTable = () => {
        if (isLoading) {
            return <TableCardSkeleton rows={8}/>;
        }

        if (isError && rows.length === 0) {
            return (
                <NoResultsSplash
                    heading={t`Couldn't load this report`}
                    imageHref="/blank-slate/reports.svg"
                    subHeading={<p>{t`Check your connection and try again.`}</p>}
                >
                    <Button variant="default" loading={isRefreshing} onClick={onRetry}>
                        {t`Try again`}
                    </Button>
                </NoResultsSplash>
            );
        }

        if (rows.length === 0) {
            return (
                <NoResultsSplash
                    heading={t`Nothing to show yet`}
                    imageHref="/blank-slate/reports.svg"
                    subHeading={<p>{emptyMessage}</p>}
                />
            );
        }

        if (visibleRows.length === 0 && emptyRows) {
            return <p className={classes.allHidden}>{emptyRows.allHiddenMessage}</p>;
        }

        return (
            <TanStackTable
                data={visibleRows}
                columns={tableColumns}
                noCard
                showFooter={showTotals}
                isRefreshing={isRefreshing}
                dataTestId="report-table"
            />
        );
    };

    return (
        <div className={classes.report}>
            {filters && <div className={classes.filters}>{filters}</div>}

            <div className={classes.overview}>
                <div className={classes.header}>
                    <div className={classes.heading}>
                        <p className={classes.scope}>{scope}</p>
                        <p className={classes.description}>{description}</p>
                    </div>
                    <Button
                        variant="default"
                        className={classes.exportButton}
                        leftSection={<IconDownload size={16} stroke={1.5}/>}
                        onClick={handleExport}
                        loading={isExporting}
                        disabled={rows.length === 0}
                    >
                        {t`Export CSV`}
                    </Button>
                    <ActionIcon
                        variant="default"
                        size={36}
                        className={classes.exportIcon}
                        aria-label={t`Export CSV`}
                        onClick={handleExport}
                        loading={isExporting}
                        disabled={rows.length === 0}
                    >
                        <IconDownload size={16} stroke={1.5}/>
                    </ActionIcon>
                </div>
                {showSummary && (
                    <dl className={classes.summary} data-testid="report-summary">
                        {isLoading
                            ? Array.from({length: 4}, (_, index) => (
                                <div key={index} className={classes.summaryItem}>
                                    <Skeleton height={10} width={72} radius="sm"/>
                                    <Skeleton height={index === 0 ? 30 : 22} width={index === 0 ? 150 : 80} radius="sm" mt={10}/>
                                </div>
                            ))
                            : summaryItems.map(item => (
                                <div key={item.label} className={classes.summaryItem}>
                                    <dt>{item.label}</dt>
                                    <dd className={Number(item.value) === 0 ? classes.zero : undefined}>
                                        {formatValue(item.kind, item.value, totalsCurrency ?? currency)}
                                    </dd>
                                </div>
                            ))}
                    </dl>
                )}
                {chart && showChart && (
                    <figure className={classes.chart}>
                        <figcaption>{chart.label}</figcaption>
                        <BarChart
                            h={180}
                            data={chartData}
                            dataKey="date"
                            series={[{name: 'value', color: 'primary.5', label: chart.label}]}
                            valueFormatter={value => formatValue(chart.kind, value, totalsCurrency ?? currency)}
                            gridAxis="x"
                            tickLine="none"
                            withLegend={false}
                            barProps={{radius: [3, 3, 0, 0], maxBarSize: 28}}
                            xAxisProps={{minTickGap: 40, padding: {left: 20, right: 20}}}
                            yAxisProps={{
                                width: 56,
                                tickMargin: 8,
                                tickCount: 4,
                                allowDecimals: false,
                                tickFormatter: formatAxisTick,
                            }}
                        />
                    </figure>
                )}
            </div>

            {notice}
            {hasMixedCurrencies && !isPaged && (
                <p className={classes.note}>
                    <IconInfoCircle size={16} stroke={1.5} aria-hidden/>
                    {t`Money totals are hidden because these rows are in more than one currency. Choose a currency to see totals.`}
                </p>
            )}
            {isPaged && (
                <p className={classes.note}>
                    <IconInfoCircle size={16} stroke={1.5} aria-hidden/>
                    {t`This report spans several pages, so totals and sorting are turned off. Export the CSV to work with every row.`}
                </p>
            )}

            <div className={classes.table}>
                {renderTable()}
                {!isLoading && rows.length > 0 && (
                    <footer className={classes.footer}>
                        <span className={classes.rowCount}>{rowCount()}</span>
                        <div className={classes.footerControls}>
                            {emptyRows && (
                                <Switch
                                    size="xs"
                                    label={emptyRows.toggleLabel}
                                    checked={showEmptyRows}
                                    onChange={event => setParams({empty: event.currentTarget.checked ? 'show' : null})}
                                />
                            )}
                            {pagination && isPaged && (
                                <Pagination
                                    size="sm"
                                    siblings={1}
                                    boundaries={1}
                                    value={pagination.page}
                                    total={pagination.lastPage}
                                    onChange={pagination.onChange}
                                />
                            )}
                        </div>
                    </footer>
                )}
            </div>
        </div>
    );
};
