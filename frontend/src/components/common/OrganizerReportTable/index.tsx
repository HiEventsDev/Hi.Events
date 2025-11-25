import {ComboboxItem, Group, Select, Skeleton, Table as MantineTable} from '@mantine/core';
import {t} from '@lingui/macro';
import {DatePickerInput} from "@mantine/dates";
import {IconArrowDown, IconArrowsSort, IconArrowUp, IconCalendar} from "@tabler/icons-react";
import {useMemo, useState} from "react";
import {PageTitle} from "../PageTitle";
import {DownloadCsvButton} from "../DownloadCsvButton";
import {Table, TableHead} from "../Table";
import '@mantine/dates/styles.css';
import {useGetOrganizerReport} from "../../../queries/useGetOrganizerReport.ts";
import {useParams} from "react-router";
import {Organizer} from "../../../types.ts";
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';
import classes from './OrganizerReportTable.module.scss';

dayjs.extend(utc);
dayjs.extend(timezone);

interface Column<T> {
    key: keyof T;
    label: string;
    render?: (value: any, row: T) => React.ReactNode;
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
    downloadFileName?: string;
    showCustomDatePicker?: boolean;
    showCurrencyFilter?: boolean;
    availableCurrencies?: string[];
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

const OrganizerReportTable = <T extends Record<string, any>>({
                                                                 title,
                                                                 columns,
                                                                 showDateFilter = true,
                                                                 defaultStartDate = new Date(new Date().setMonth(new Date().getMonth() - 3)),
                                                                 defaultEndDate = new Date(),
                                                                 onDateRangeChange,
                                                                 enableDownload = true,
                                                                 downloadFileName = 'report.csv',
                                                                 showCustomDatePicker = false,
                                                                 organizer,
                                                                 showCurrencyFilter = true,
                                                                 availableCurrencies = [],
                                                             }: OrganizerReportProps<T>) => {
    const timezone = organizer.timezone || 'UTC';
    const [dateRange, setDateRange] = useState<[Date | null, Date | null]>([
        dayjs(defaultStartDate).tz(timezone).toDate(),
        dayjs(defaultEndDate).tz(timezone).toDate()
    ]);
    const [selectedPeriod, setSelectedPeriod] = useState('90d');
    const [showDatePickerInput, setShowDatePickerInput] = useState(showCustomDatePicker);
    const [sortField, setSortField] = useState<keyof T | null>(null);
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc' | null>(null);
    const [selectedCurrency, setSelectedCurrency] = useState<string | null>(null);
    const {reportType, organizerId} = useParams();
    const reportQuery = useGetOrganizerReport(organizerId, reportType || '', dateRange[0], dateRange[1], selectedCurrency);
    const data = (reportQuery.data || []) as T[];

    const calculateDateRange = (period: string): [Date | null, Date | null] => {
        if (period === 'custom') {
            setShowDatePickerInput(true);
            return dateRange;
        }
        setShowDatePickerInput(false);

        let end = dayjs().tz(timezone).endOf('day');
        let start = dayjs().tz(timezone);

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
        onDateRangeChange?.(newRange);
    };

    const handleDateRangeChange = (newRange: [Date | null, Date | null]) => {
        const [start, end] = newRange;
        const tzStart = start ? dayjs(start).tz(timezone) : null;
        const tzEnd = end ? dayjs(end).tz(timezone) : null;

        const tzRange: [Date | null, Date | null] = [
            tzStart?.toDate() || null,
            tzEnd?.toDate() || null
        ];

        setDateRange(tzRange);
        onDateRangeChange?.(tzRange);
    };

    const handleCurrencyChange = (value: string | null) => {
        setSelectedCurrency(value === '' ? null : value);
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

    const csvHeaders = columns.map(col => col.label);
    const csvData = sortedData.map(row =>
        columns.map(col => {
            const value = row[col.key];
            return typeof value === 'number' ? value.toString() : value;
        })
    );

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
                            minDate={dayjs().subtract(1, 'year').tz(timezone).toDate()}
                            maxDate={dayjs().tz(timezone).toDate()}
                            className={classes.datePicker}
                        />
                    )}
                    {enableDownload && (
                        <DownloadCsvButton
                            headers={csvHeaders}
                            data={csvData}
                            filename={downloadFileName}
                            className={classes.downloadButton}
                        />
                    )}
                </Group>
            </Group>
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
                                    {t`${column.label}`}
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
                                        ? column.render(row[column.key], row)
                                        : row[column.key]
                                    }
                                </MantineTable.Td>
                            ))}
                        </MantineTable.Tr>
                    ))}
                </MantineTable.Tbody>
            </Table>
        </>
    );
};

export default OrganizerReportTable;
