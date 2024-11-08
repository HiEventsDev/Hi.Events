import {Group, LoadingOverlay, Paper, Table as MantineTable} from '@mantine/core';
import {t} from '@lingui/macro';
import {DatePickerInput} from "@mantine/dates";
import {IconArrowDown, IconArrowsSort, IconArrowUp, IconCalendar} from "@tabler/icons-react";
import React, {useState} from "react";
import {PageTitle} from "../PageTitle";
import {DownloadCsvButton} from "../DownloadCsvButton";
import {Table, TableHead} from "../Table";
import '@mantine/dates/styles.css';
import {useGetEventReport} from "../../../queries/useGetEventReport.ts";
import {useParams} from "react-router-dom";

interface Column<T> {
    key: keyof T;
    label: string;
    render?: (value: any, row: T) => React.ReactNode;
    sortable?: boolean;
}

interface ReportProps<T> {
    title: string;
    columns: Column<T>[];
    isLoading?: boolean;
    showDateRange?: boolean;
    defaultStartDate?: Date;
    defaultEndDate?: Date;
    onDateRangeChange?: (range: [Date | null, Date | null]) => void;
    enableDownload?: boolean;
    downloadFileName?: string;
}

const ReportTable = <T extends Record<string, any>>({
                                                        title,
                                                        columns,
                                                        isLoading = false,
                                                        showDateRange = true,
                                                        defaultStartDate = new Date(new Date().setMonth(new Date().getMonth() - 3)),
                                                        defaultEndDate = new Date(),
                                                        onDateRangeChange,
                                                        enableDownload = true,
                                                        downloadFileName = 'report.csv'
                                                    }: ReportProps<T>) => {
    const [dateRange, setDateRange] = useState<[Date | null, Date | null]>([defaultStartDate, defaultEndDate]);
    const [sortField, setSortField] = useState<keyof T | null>(null);
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc' | null>(null);
    const {reportType, eventId} = useParams();
    const reportQuery = useGetEventReport(eventId, reportType, dateRange[0], dateRange[1]);
    const data = (reportQuery.data || []) as T[];

    if (isLoading) {
        return (
            <Paper p="md" pos="relative">
                <LoadingOverlay visible={true}/>
            </Paper>
        );
    }

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

    const handleDateRangeChange = (newRange: [Date | null, Date | null]) => {
        setDateRange(newRange);
        onDateRangeChange?.(newRange);
    };

    const sortedData = [...data].sort((a, b) => {
        if (!sortField || !sortDirection) return 0;

        const aValue = a[sortField];
        const bValue = b[sortField];

        if (typeof aValue === 'string' && typeof bValue === 'string') {
            return sortDirection === 'asc'
                ? aValue.toLowerCase().localeCompare(bValue.toLowerCase())
                : bValue.toLowerCase().localeCompare(aValue.toLowerCase());
        }

        if (sortDirection === 'asc') {
            return aValue > bValue ? 1 : -1;
        } else {
            return aValue < bValue ? 1 : -1;
        }
    });

    // Prepare CSV data
    const csvHeaders = columns.map(col => col.label);
    const csvData = sortedData.map(row =>
        columns.map(col => {
            const value = row[col.key];
            return typeof value === 'number' ? value.toString() : value;
        })
    );

    const loadingMessage = () => {
        const wrapper = (message: React.ReactNode) => {
            return (
                <MantineTable.Tr>
                    <MantineTable.Td colSpan={columns.length} align="center">
                        {message}
                    </MantineTable.Td>
                </MantineTable.Tr>
            );
        }

        if (reportQuery.isLoading) {
            return wrapper(t`Loading...`);
        }

        if (showDateRange && (!dateRange[0] || !dateRange[1])) {
            return wrapper(t`No data to show. Please select a date range`);
        }

        if (!showDateRange && dateRange[0] && dateRange[1]) {
            return wrapper(t`No data available`);
        }
    };

    return (
        <>
            <Group justify="space-between" mb="md">
                <PageTitle>{t`${title}`}</PageTitle>
                <Group justify="flex-end" align="center">
                    {showDateRange && (
                        <DatePickerInput
                            style={{minWidth: '305px', marginBottom: '0'}}
                            leftSection={<IconCalendar stroke={1.5} size={20}/>}
                            type="range"
                            placeholder="Pick dates range"
                            value={dateRange}
                            onChange={handleDateRangeChange}
                            minDate={new Date(new Date().setFullYear(new Date().getFullYear() - 1))}
                            maxDate={new Date()}
                        />
                    )}
                    {enableDownload && (
                        <DownloadCsvButton
                            headers={csvHeaders}
                            data={csvData}
                            filename={downloadFileName}
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
                                <Group gap="xs">
                                    {t`${column.label}`}
                                    {column.sortable && getSortIcon(column.key)}
                                </Group>
                            </MantineTable.Th>
                        ))}
                    </MantineTable.Tr>
                </TableHead>
                <MantineTable.Tbody>

                    {!sortedData.length && loadingMessage()}

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

export default ReportTable;
