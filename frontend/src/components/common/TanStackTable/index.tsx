import {ColumnDef, flexRender, getCoreRowModel, useReactTable, VisibilityState,} from '@tanstack/react-table';
import {Table as MantineTable} from '@mantine/core';
import React, {useEffect, useState} from 'react';
import {Card} from '../Card';
import classes from './TanStackTable.module.scss';

export interface TanStackTableColumnMeta {
    sticky?: 'left' | 'right';
    headerStyle?: React.CSSProperties;
    cellStyle?: React.CSSProperties;
}

export type TanStackTableColumn<TData> = ColumnDef<TData> & {
    meta?: TanStackTableColumnMeta;
};

interface TanStackTableProps<TData> {
    data: TData[];
    columns: TanStackTableColumn<TData>[];
    storageKey?: string;
    enableColumnVisibility?: boolean;
    renderColumnVisibilityToggle?: (table: ReturnType<typeof useReactTable<TData>>) => React.ReactNode;
    hideHeader?: boolean;
    noCard?: boolean;
    rowStyle?: (row: TData) => React.CSSProperties | undefined;
}

export function TanStackTable<TData>({
                                         data,
                                         columns,
                                         storageKey,
                                         enableColumnVisibility = false,
                                         renderColumnVisibilityToggle,
                                         hideHeader = false,
                                         noCard = false,
                                         rowStyle,
                                     }: TanStackTableProps<TData>) {
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(() => {
        if (storageKey && enableColumnVisibility) {
            const stored = localStorage.getItem(`${storageKey}-column-visibility`);
            if (stored) {
                try {
                    return JSON.parse(stored);
                } catch {
                    return {};
                }
            }
        }
        return {};
    });

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        state: {
            columnVisibility,
        },
        onColumnVisibilityChange: setColumnVisibility,
        enableHiding: enableColumnVisibility,
    });

    useEffect(() => {
        if (storageKey && enableColumnVisibility) {
            localStorage.setItem(`${storageKey}-column-visibility`, JSON.stringify(columnVisibility));
        }
    }, [columnVisibility, storageKey, enableColumnVisibility]);

    const tableContent = (
        <MantineTable.ScrollContainer minWidth={200} scrollAreaProps={{
            type: 'hover',
        }}>
            <MantineTable className={classes.table}>
                {!hideHeader && (
                    <MantineTable.Thead className={classes.tableHead}>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <MantineTable.Tr key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    const columnMeta = header.column.columnDef.meta as TanStackTableColumnMeta | undefined;
                                    const stickyClass = columnMeta?.sticky === 'left'
                                        ? classes.stickyLeft
                                        : columnMeta?.sticky === 'right'
                                            ? classes.stickyRight
                                            : '';

                                    return (
                                        <MantineTable.Th
                                            key={header.id}
                                            className={stickyClass}
                                            style={{
                                                ...columnMeta?.headerStyle,
                                            }}
                                        >
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                    header.column.columnDef.header,
                                                    header.getContext()
                                                )}
                                        </MantineTable.Th>
                                    );
                                })}
                            </MantineTable.Tr>
                        ))}
                    </MantineTable.Thead>
                )}
                <MantineTable.Tbody>
                    {table.getRowModel().rows.map((row) => (
                        <MantineTable.Tr key={row.id} style={rowStyle?.(row.original)}>
                            {row.getVisibleCells().map((cell) => {
                                const columnMeta = cell.column.columnDef.meta as TanStackTableColumnMeta | undefined;
                                const stickyClass = columnMeta?.sticky === 'left'
                                    ? classes.stickyLeft
                                    : columnMeta?.sticky === 'right'
                                        ? classes.stickyRight
                                        : '';

                                return (
                                    <MantineTable.Td
                                        key={cell.id}
                                        className={stickyClass}
                                        style={{
                                            ...columnMeta?.cellStyle,
                                        }}
                                    >
                                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                    </MantineTable.Td>
                                );
                            })}
                        </MantineTable.Tr>
                    ))}
                </MantineTable.Tbody>
            </MantineTable>
        </MantineTable.ScrollContainer>
    );

    return (
        <div className={classes.tableWrapper}>
            {enableColumnVisibility && renderColumnVisibilityToggle && (
                <div className={classes.toolbar}>
                    {renderColumnVisibilityToggle(table)}
                </div>
            )}
            {noCard ? tableContent : <Card className={classes.card}>{tableContent}</Card>}
        </div>
    );
}
