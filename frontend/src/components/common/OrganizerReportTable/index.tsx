import {Button, ComboboxItem, Group, Select, Skeleton, Table as MantineTable, Text} from '@mantine/core';
import {t} from '@lingui/macro';
import {DatePickerInput} from "@mantine/dates";
import {IconArrowDown, IconArrowsSort, IconArrowUp, IconCalendar, IconDownload} from "@tabler/icons-react";
import {useMemo, useState} from "react";
import {PageTitle} from "../PageTitle";
import {Table, TableHead} from "../Table";
import {Pagination} from "../Pagination";
import '@mantine/dates/styles.css';
import {useGetOrganizerReport} from "../../../queries/useGetOrganizerReport.ts";
import {useParams} from "react-router";
import {Organizer} from "../../../types.ts";
import {organizerClient} from "../../../api/organizer.client.ts";
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';
import classes from './OrganizerReportTable.module.scss';
import {downloadBinary} from "../../../utilites/download.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";

dayjs.extend(utc);
dayjs.extend(timezone);

export interface RenderContext {
    currency: string;
}

interface Column<T> {
    key: keyof T;
    label: string;
    render?: (value: any, row: T, context: RenderContext) => React.ReactNode;
    sortable?: boolean;
}

interface OrganizerReportProps<T> {
    title: string;
    columns: Column<T>[];
    organizer: Organizer;
    isLoading?: boolean;
    showDateFilter?: boolean;
    defaultStartDate?: Date;
    defaultEndDate?: Date;
    onDateRangeChange?: (range: [Date | null, Date | null]) => void;
    enableDownload?: boolean;
    showCustomDatePicker?: boolean;
    showCurrencyFilter?: boolean;
    availableCurrencies?: string[];
    eventId?: number | null;
}

const TIME_PERIODS = [
    {value: '24h', label: t`Last 24 hours`},
    {value: '48h', label: t`Last 48 hours`},
    {value: '7d', label: t`Last 7 days`},
    {value: '14d', label: t`Last 14 days`},
    {value: '30d', label: t`Last 30 days`},
    {value: '90d', label: t`Last 90 days`},
    {value: '6m', label: t`Last 6 months`},
    {value: 'ytd', label: t`Year to date`},
    {value: '12m', label: t`Last 12 months`},
    {value: 'custom', label: t`Custom Range`}
];

const ROWS_PER_PAGE = 1000;

const OrganizerReportTable = <T extends Record<string, any>>({
                                                                 title,
                                                                 columns,
                                                                 showDateFilter = true,
                                                                 defaultStartDate = new Date(new Date().setMonth(new Date().getMonth() - 3)),
                                                                 defaultEndDate = new Date(),
                                                                 onDateRangeChange,
                                                                 enableDownload = true,
                                                                 organizer,
                                                                 showCurrencyFilter = true,
                                                                 availableCurrencies = [],
                                                                 eventId,
                                                             }: OrganizerReportProps<T>) => {
    const tz = organizer.timezone || 'UTC';
    const [dateRange, setDateRange] = useState<[Date | null, Date | null]>([
        dayjs(defaultStartDate).tz(tz).toDate(),
        dayjs(defaultEndDate).tz(tz).toDate()
    ]);
    const [selectedPeriod, setSelectedPeriod] = useState('90d');
    const [showDatePickerInput, setShowDatePickerInput] = useState(false);
    const [sortField, setSortField] = useState<keyof T | null>(null);
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc' | null>(null);
    const [selectedCurrency, setSelectedCurrency] = useState<string | null>(null);
    const [currentPage, setCurrentPage] = useState(1);
    const {reportType, organizerId} = useParams();

    const reportQuery = useGetOrganizerReport(
        organizerId,
        reportType || '',
        dateRange[0],
        dateRange[1],
        selectedCurrency,
        eventId,
        currentPage,
        ROWS_PER_PAGE
    );

    const reportData = reportQuery.data;
    const data = (reportData?.data || []) as T[];
    const pagination = reportData?.pagination;

    const calculateDateRange = (period: string): [Date | null, Date | null] => {
        if (period === 'custom') {
            setShowDatePickerInput(true);
            return dateRange;
        }
        setShowDatePickerInput(false);

        let end = dayjs().tz(tz).endOf('day');
        let start = dayjs().tz(tz);

        switch (period) {
            case '24h':
                start = start.startOf('day');
                end = start.endOf('day');
                break;
            case '48h':
                start = start.subtract(1, 'day').startOf('day');
                end = start.endOf('day').add(1, 'day');
                break;
            case '7d':
                start = start.subtract(6, 'day').startOf('day');
                break;
            case '14d':
                start = start.subtract(13, 'day').startOf('day');
                break;
            case '30d':
                start = start.subtract(29, 'day').startOf('day');
                break;
            case '90d':
                start = start.subtract(89, 'day').startOf('day');
                break;
            case '6m':
                start = start.subtract(6, 'month').startOf('day');
                break;
            case 'ytd':
                start = start.startOf('year');
                break;
            case '12m':
                start = start.subtract(12, 'month').startOf('day');
                break;
            default:
                return [null, null];
        }

        return [start.toDate(), end.toDate()];
    };

    const handlePeriodChange = (value: string | null, _: ComboboxItem) => {
        if (!value) return;
        setSelectedPeriod(value);
        const newRange = calculateDateRange(value);
        setDateRange(newRange);
        setCurrentPage(1);
        onDateRangeChange?.(newRange);
    };

    const handleDateRangeChange = (newRange: [Date | null, Date | null]) => {
        const [start, end] = newRange;
        const tzStart = start ? dayjs(start).tz(tz) : null;
        const tzEnd = end ? dayjs(end).tz(tz) : null;

        const tzRange: [Date | null, Date | null] = [
            tzStart?.toDate() || null,
            tzEnd?.toDate() || null
        ];

        setDateRange(tzRange);
        setCurrentPage(1);
        onDateRangeChange?.(tzRange);
    };

    const handleCurrencyChange = (value: string | null) => {
        setSelectedCurrency(value === '' ? null : value);
        setCurrentPage(1);
    };

    const handlePageChange = (page: number) => {
        setCurrentPage(page);
    };

    const [isExporting, setIsExporting] = useState(false);

    const handleExport = async () => {
        if (!organizerId || !reportType) return;

        setIsExporting(true);
        try {
            const blob = await organizerClient.exportOrganizerReport(
                organizerId,
                reportType,
                dateRange[0]?.toISOString(),
                dateRange[1]?.toISOString(),
                selectedCurrency,
                eventId
            );
            const filename = `${reportType}_${dayjs().format('YYYY-MM-DD_HH-mm-ss')}.csv`;
            downloadBinary(blob, filename);
            showSuccess(t`Export successful`);
        } catch {
            showError(t`Failed to export report. Please try again.`);
        } finally {
            setIsExporting(false);
        }
    };

    const handleSort = (field: keyof T) => {
        if (sortField === field) {
            if (sortDirection === 'asc') setSortDirection('desc');
            else if (sortDirection === 'desc') {
                setSortDirection(null);
                setSortField(null);
            } else setSortDirection('asc');
        } else {
            setSortField(field);
            setSortDirection('asc');
        }
    };

    const getSortIcon = (field: keyof T) => {
        if (sortField !== field) return <IconArrowsSort size={16}/>;
        if (sortDirection === 'asc') return <IconArrowUp size={16}/>;
        if (sortDirection === 'desc') return <IconArrowDown size={16}/>;
        return <IconArrowsSort size={16} className="ml-2 text-gray-400"/>;
    };

    const sortedData = useMemo(() => {
        return [...data].sort((a, b) => {
            if (!sortField || !sortDirection) return 0;
            const aValue = a[sortField];
            const bValue = b[sortField];

            const aNum = Number(aValue);
            const bNum = Number(bValue);

            if (!isNaN(aNum) && !isNaN(bNum)) {
                return sortDirection === 'asc' ? aNum - bNum : bNum - aNum;
            }

            if (typeof aValue === 'string' && typeof bValue === 'string') {
                return sortDirection === 'asc'
                    ? aValue.toLowerCase().localeCompare(bValue.toLowerCase())
                    : bValue.toLowerCase().localeCompare(aValue.toLowerCase());
            }

            return 0;
        });
    }, [data, sortField, sortDirection]);

    const emptyStateMessage = () => {
        const wrapper = (message: React.ReactNode) => (
            <MantineTable.Tr>
                <MantineTable.Td colSpan={columns.length} align="center" py="xl">
                    {message}
                </MantineTable.Td>
            </MantineTable.Tr>
        );

        if (reportQuery.isLoading) {
            return wrapper(t`Loading...`);
        }

        if (showDateFilter && (!dateRange[0] || !dateRange[1])) {
            return wrapper(t`Please select a date range`);
        }

        return wrapper(t`No data found for the selected filters. Try adjusting the date range or currency.`);
    };

    if (reportQuery.isLoading) {
        return (
            <>
                <Group justify="space-between" mb="xl">
                    <Skeleton height={32} width={200}/>
                    <Group gap="sm">
                        <Skeleton height={32} width={200}/>
                        <Skeleton height={32} width={130}/>
                    </Group>
                </Group>
                <Skeleton height={300} radius="md"/>
            </>
        );
    }

    const currencyOptions = [
        {value: '', label: t`All Currencies`},
        ...availableCurrencies.map(curr => ({value: curr, label: curr}))
    ];

    const totalPages = pagination?.last_page || 1;
    const totalRows = pagination?.total || 0;

    return (
        <>
            <Group justify="space-between" mb="md">
                <PageTitle>{title}</PageTitle>
                <Group justify="flex-end" align="center" gap="sm">
                    {showCurrencyFilter && availableCurrencies.length > 0 && (
                        <Select
                            style={{minWidth: '140px'}}
                            placeholder={t`Currency`}
                            data={currencyOptions}
                            value={selectedCurrency ?? ''}
                            onChange={handleCurrencyChange}
                            mb="0"
                            className={classes.currencySelect}
                        />
                    )}
                    {showDateFilter && (
                        <Select
                            style={{minWidth: '200px'}}
                            placeholder={t`Select time period`}
                            data={TIME_PERIODS}
                            value={selectedPeriod}
                            onChange={handlePeriodChange}
                            leftSection={<IconCalendar stroke={1.5} size={20}/>}
                            mb="0"
                            className={classes.periodSelect}
                        />
                    )}
                    {showDateFilter && showDatePickerInput && (
                        <DatePickerInput
                            style={{minWidth: '305px', marginBottom: '0'}}
                            leftSection={<IconCalendar stroke={1.5} size={20}/>}
                            type="range"
                            placeholder="Pick dates range"
                            value={dateRange}
                            onChange={handleDateRangeChange}
                            minDate={dayjs().subtract(1, 'year').tz(tz).toDate()}
                            maxDate={dayjs().tz(tz).toDate()}
                            className={classes.datePicker}
                        />
                    )}
                    {enableDownload && (
                        <Button
                            leftSection={<IconDownload size={16}/>}
                            variant="light"
                            onClick={handleExport}
                            loading={isExporting}
                            className={classes.downloadButton}
                        >
                            {t`Export CSV`}
                        </Button>
                    )}
                </Group>
            </Group>

            {totalRows > 0 && (
                <Text size="sm" c="dimmed" mb="sm">
                    {t`Showing ${sortedData.length} of ${totalRows} records`}
                    {totalPages > 1 && ` (${t`Page`} ${currentPage} ${t`of`} ${totalPages})`}
                </Text>
            )}

            <Table>
                <TableHead>
                    <MantineTable.Tr>
                        {columns.map((column) => (
                            <MantineTable.Th
                                key={String(column.key)}
                                onClick={column.sortable ? () => handleSort(column.key) : undefined}
                                style={{cursor: column.sortable ? 'pointer' : 'default', minWidth: '180px'}}
                            >
                                <Group gap="xs" wrap={'nowrap'}>
                                    {column.label}
                                    {column.sortable && getSortIcon(column.key)}
                                </Group>
                            </MantineTable.Th>
                        ))}
                    </MantineTable.Tr>
                </TableHead>
                <MantineTable.Tbody>
                    {!sortedData.length && emptyStateMessage()}
                    {sortedData.map((row, index) => (
                        <MantineTable.Tr key={index}>
                            {columns.map((column) => (
                                <MantineTable.Td key={String(column.key)}>
                                    {column.render
                                        ? column.render(row[column.key], row, { currency: selectedCurrency || organizer.currency || 'USD' })
                                        : row[column.key]
                                    }
                                </MantineTable.Td>
                            ))}
                        </MantineTable.Tr>
                    ))}
                </MantineTable.Tbody>
            </Table>

            {totalPages > 1 && (
                <Pagination
                    value={currentPage}
                    onChange={handlePageChange}
                    total={totalPages}
                />
            )}
        </>
    );
};

export default OrganizerReportTable;
