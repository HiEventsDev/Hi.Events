import {ColumnDef, flexRender, getCoreRowModel, Updater, useReactTable, VisibilityState,} from '@tanstack/react-table';
import {Checkbox, Table as MantineTable} from '@mantine/core';
import {t} from '@lingui/macro';
import React, {useCallback, useEffect, useRef, useState} from 'react';
import {Card} from '../Card';
import classes from './TanStackTable.module.scss';

export interface TanStackTableColumnMeta {
    label?: string;
    sticky?: 'left' | 'right';
    headerStyle?: React.CSSProperties;
    cellStyle?: React.CSSProperties;
}

export type TanStackTableColumn<TData> = ColumnDef<TData> & {
    meta?: TanStackTableColumnMeta;
};

interface TanStackTableSelection<TData> {
    selectedIds: ReadonlySet<string>;
    onChange: (selectedIds: Set<string>) => void;
    getRowId: (row: TData) => string;
    getRowLabel: (row: TData) => string;
}

interface TanStackTableProps<TData> {
    data: TData[];
    columns: TanStackTableColumn<TData>[];
    storageKey?: string;
    enableColumnVisibility?: boolean;
    renderColumnVisibilityToggle?: (table: ReturnType<typeof useReactTable<TData>>) => React.ReactNode;
    hideHeader?: boolean;
    noCard?: boolean;
    rowStyle?: (row: TData) => React.CSSProperties | undefined;
    rowProps?: (row: TData) => React.ComponentPropsWithoutRef<'tr'> & Record<`data-${string}`, string | boolean | undefined>;
    selection?: TanStackTableSelection<TData>;
    columnVisibility?: VisibilityState;
    onColumnVisibilityChange?: (columnVisibility: VisibilityState) => void;
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
                                         rowProps,
                                         selection,
                                         columnVisibility: controlledColumnVisibility,
                                         onColumnVisibilityChange,
                                     }: TanStackTableProps<TData>) {
    const isVisibilityControlled = controlledColumnVisibility !== undefined;
    const [storedColumnVisibility, setStoredColumnVisibility] = useState<VisibilityState>(() => {
        if (storageKey && enableColumnVisibility && !isVisibilityControlled) {
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

    const columnVisibility = controlledColumnVisibility ?? storedColumnVisibility;
    const setColumnVisibility = (updater: Updater<VisibilityState>) => {
        const next = typeof updater === 'function' ? updater(columnVisibility) : updater;

        if (isVisibilityControlled) {
            onColumnVisibilityChange?.(next);
        } else {
            setStoredColumnVisibility(next);
        }
    };

    const lastToggledIndexRef = useRef<number | null>(null);
    const viewportRef = useRef<HTMLDivElement>(null);
    const [showStartShadow, setShowStartShadow] = useState(false);
    const [showEndShadow, setShowEndShadow] = useState(false);

    const updateStickyShadows = useCallback(() => {
        const viewport = viewportRef.current;
        if (!viewport) {
            return;
        }
        const {scrollLeft, clientWidth, scrollWidth} = viewport;
        setShowStartShadow(scrollLeft > 1);
        setShowEndShadow(scrollWidth - clientWidth - scrollLeft > 1);
    }, []);

    useEffect(() => {
        const viewport = viewportRef.current;
        if (!viewport) {
            return;
        }
        updateStickyShadows();
        const resizeObserver = new ResizeObserver(updateStickyShadows);
        resizeObserver.observe(viewport);
        if (viewport.firstElementChild) {
            resizeObserver.observe(viewport.firstElementChild);
        }
        return () => resizeObserver.disconnect();
    }, [updateStickyShadows]);

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
        if (storageKey && enableColumnVisibility && !isVisibilityControlled) {
            try {
                localStorage.setItem(`${storageKey}-column-visibility`, JSON.stringify(storedColumnVisibility));
            } catch {
                return;
            }
        }
    }, [storedColumnVisibility, storageKey, enableColumnVisibility, isVisibilityControlled]);

    const pageIds = selection ? data.map(selection.getRowId) : [];
    const selectedOnPage = selection ? pageIds.filter(id => selection.selectedIds.has(id)).length : 0;
    const isSelecting = !!selection && selection.selectedIds.size > 0;

    const togglePage = () => {
        if (!selection) {
            return;
        }
        const next = new Set(selection.selectedIds);
        const selectAll = selectedOnPage < pageIds.length;
        pageIds.forEach(id => selectAll ? next.add(id) : next.delete(id));
        lastToggledIndexRef.current = null;
        selection.onChange(next);
    };

    const toggleRow = (index: number, isRange: boolean) => {
        if (!selection) {
            return;
        }
        const next = new Set(selection.selectedIds);
        const shouldSelect = !next.has(pageIds[index]);
        const anchor = lastToggledIndexRef.current;
        const [from, to] = isRange && anchor !== null ? [Math.min(anchor, index), Math.max(anchor, index)] : [index, index];

        for (let position = from; position <= to; position++) {
            if (shouldSelect) {
                next.add(pageIds[position]);
            } else {
                next.delete(pageIds[position]);
            }
        }

        lastToggledIndexRef.current = index;
        selection.onChange(next);
    };

    const pinsSelection = !!selection && columns.some(column => (column.meta as TanStackTableColumnMeta | undefined)?.sticky === 'left');
    const selectCellClass = pinsSelection ? `${classes.selectCell} ${classes.pinnedSelect}` : classes.selectCell;
    const stickyLeftClass = pinsSelection ? `${classes.stickyLeft} ${classes.afterSelect}` : classes.stickyLeft;

    const tableContent = (
        <MantineTable.ScrollContainer minWidth={200} scrollAreaProps={{
            type: 'hover',
            viewportRef: viewportRef,
            onScrollPositionChange: updateStickyShadows,
        }}>
            <MantineTable
                className={`${classes.table} ${showStartShadow ? classes.showStartShadow : ''} ${showEndShadow ? classes.showEndShadow : ''}`}
                data-selecting={isSelecting || undefined}
            >
                {!hideHeader && (
                    <MantineTable.Thead className={classes.tableHead}>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <MantineTable.Tr key={headerGroup.id}>
                                {selection && (
                                    <MantineTable.Th className={selectCellClass}>
                                        <Checkbox
                                            size="xs"
                                            aria-label={t`Select all rows on this page`}
                                            checked={pageIds.length > 0 && selectedOnPage === pageIds.length}
                                            indeterminate={selectedOnPage > 0 && selectedOnPage < pageIds.length}
                                            onChange={togglePage}
                                        />
                                    </MantineTable.Th>
                                )}
                                {headerGroup.headers.map((header) => {
                                    const columnMeta = header.column.columnDef.meta as TanStackTableColumnMeta | undefined;
                                    const stickyClass = columnMeta?.sticky === 'left'
                                        ? stickyLeftClass
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
                    {table.getRowModel().rows.map((row, index) => (
                        <MantineTable.Tr
                            key={row.id}
                            {...rowProps?.(row.original)}
                            style={rowStyle?.(row.original)}
                            data-selected={selection?.selectedIds.has(selection.getRowId(row.original)) || undefined}
                        >
                            {selection && (
                                <MantineTable.Td
                                    className={selectCellClass}
                                    onClick={event => {
                                        event.stopPropagation();
                                        toggleRow(index, event.shiftKey);
                                    }}
                                >
                                    <Checkbox
                                        size="xs"
                                        aria-label={selection.getRowLabel(row.original)}
                                        checked={selection.selectedIds.has(selection.getRowId(row.original))}
                                        onChange={() => undefined}
                                        onClick={event => {
                                            event.stopPropagation();
                                            toggleRow(index, event.shiftKey);
                                        }}
                                    />
                                </MantineTable.Td>
                            )}
                            {row.getVisibleCells().map((cell) => {
                                const columnMeta = cell.column.columnDef.meta as TanStackTableColumnMeta | undefined;
                                const stickyClass = columnMeta?.sticky === 'left'
                                    ? stickyLeftClass
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
