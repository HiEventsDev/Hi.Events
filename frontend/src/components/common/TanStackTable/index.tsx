import {ColumnDef, getCoreRowModel, Updater, useReactTable, VisibilityState,} from '@tanstack/react-table';
import {Checkbox, Table as MantineTable} from '@mantine/core';
import {t} from '@lingui/macro';
import React, {useCallback, useEffect, useRef, useState} from 'react';
import {createPortal} from 'react-dom';
import classNames from 'classnames';
import {ColumnVisibilityToggle} from '../ColumnVisibilityToggle';
import classes from './TanStackTable.module.scss';

export interface TanStackTableColumnMeta {
    label?: string;
    sticky?: 'left' | 'right';
    align?: 'right';
    interactive?: boolean;
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
    defaultColumnVisibility?: VisibilityState;
    columnToggleSlot?: HTMLElement | null;
    hideHeader?: boolean;
    noCard?: boolean;
    showFooter?: boolean;
    isRefreshing?: boolean;
    rowLines?: 1 | 2;
    dataTestId?: string;
    onRowClick?: (row: TData) => void;
    rowProps?: (row: TData) => React.ComponentPropsWithoutRef<'tr'> & Record<`data-${string}`, string | boolean | undefined>;
    selection?: TanStackTableSelection<TData>;
}

const MAX_ICON_COLUMN_WIDTH = 64;
const MAX_PINNED_SHARE = 0.7;

const renderDefinition = <TContext,>(definition: unknown, context: TContext): React.ReactNode =>
    typeof definition === 'function' ? definition(context) : definition as React.ReactNode;

const readStoredVisibility = (storageKey: string | undefined): VisibilityState => {
    if (!storageKey || typeof window === 'undefined') {
        return {};
    }
    try {
        const stored = window.localStorage.getItem(`${storageKey}-column-visibility`);
        return stored ? JSON.parse(stored) : {};
    } catch {
        return {};
    }
};

const writeStoredVisibility = (storageKey: string, visibility: VisibilityState) => {
    try {
        window.localStorage.setItem(`${storageKey}-column-visibility`, JSON.stringify(visibility));
    } catch {
        return;
    }
};

export function TanStackTable<TData>({
                                         data,
                                         columns,
                                         storageKey,
                                         enableColumnVisibility = false,
                                         defaultColumnVisibility,
                                         columnToggleSlot,
                                         hideHeader = false,
                                         noCard = false,
                                         showFooter = false,
                                         isRefreshing = false,
                                         rowLines = 1,
                                         dataTestId,
                                         onRowClick,
                                         rowProps,
                                         selection,
                                     }: TanStackTableProps<TData>) {
    const [storedColumnVisibility, setStoredColumnVisibility] = useState<VisibilityState>({});

    useEffect(() => {
        if (enableColumnVisibility) {
            setStoredColumnVisibility(readStoredVisibility(storageKey));
        }
    }, [storageKey, enableColumnVisibility]);

    const columnVisibility = {...defaultColumnVisibility, ...storedColumnVisibility};
    const setColumnVisibility = (updater: Updater<VisibilityState>) => {
        const next = typeof updater === 'function' ? updater(columnVisibility) : updater;

        setStoredColumnVisibility(next);
        if (storageKey) {
            writeStoredVisibility(storageKey, next);
        }
    };

    const lastToggledIndexRef = useRef<number | null>(null);
    const viewportRef = useRef<HTMLDivElement>(null);
    const [showStartShadow, setShowStartShadow] = useState(false);
    const [showEndShadow, setShowEndShadow] = useState(false);
    const [releaseEndPin, setReleaseEndPin] = useState(false);

    const updateStickyShadows = useCallback(() => {
        const viewport = viewportRef.current;
        if (!viewport) {
            return;
        }
        const {scrollLeft, clientWidth, scrollWidth} = viewport;
        setShowStartShadow(scrollLeft > 1);
        setShowEndShadow(scrollWidth - clientWidth - scrollLeft > 1);

        const firstRow = viewport.querySelector('tbody tr');
        const pinnedWidth = (selector: string) => Array.from(firstRow?.querySelectorAll<HTMLElement>(selector) ?? [])
            .reduce((total, cell) => total + cell.offsetWidth, 0);
        const pinnedLeft = pinnedWidth(`.${classes.stickyLeft}, .${classes.pinnedSelect}`);
        const pinnedRight = pinnedWidth(`.${classes.stickyRight}`);
        setReleaseEndPin(pinnedRight > MAX_ICON_COLUMN_WIDTH && pinnedLeft + pinnedRight > clientWidth * MAX_PINNED_SHARE);
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

    const pinsSelection = !!selection && columns.some(column => column.meta?.sticky === 'left');
    const selectCellClass = classNames(classes.selectCell, pinsSelection && classes.pinnedSelect);

    const cellClass = (meta: TanStackTableColumnMeta | undefined) => classNames(
        meta?.sticky === 'left' && classes.stickyLeft,
        meta?.sticky === 'left' && pinsSelection && classes.afterSelect,
        meta?.sticky === 'right' && classes.stickyRight,
        meta?.align === 'right' && classes.alignRight,
    );

    const stopRowClick = (event: React.MouseEvent) => event.stopPropagation();

    const tableContent = (
        <MantineTable.ScrollContainer minWidth={200} scrollAreaProps={{
            type: 'hover',
            viewportRef: viewportRef,
            onScrollPositionChange: updateStickyShadows,
        }}>
            <MantineTable
                className={classNames(classes.table, showStartShadow && classes.showStartShadow, showEndShadow && classes.showEndShadow)}
                data-selecting={isSelecting || undefined}
                data-refreshing={isRefreshing || undefined}
                data-row-lines={rowLines}
                data-release-end-pin={releaseEndPin || undefined}
                data-testid={dataTestId}
                data-clickable={!!onRowClick || undefined}
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

                                    return (
                                        <MantineTable.Th
                                            key={header.id}
                                            className={cellClass(columnMeta)}
                                        >
                                            {header.isPlaceholder ? null : renderDefinition(header.column.columnDef.header, header.getContext())}
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
                            onClick={onRowClick ? () => onRowClick(row.original) : undefined}
                            {...rowProps?.(row.original)}
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
                                const content = renderDefinition(cell.column.columnDef.cell, cell.getContext());

                                return (
                                    <MantineTable.Td
                                        key={cell.id}
                                        className={cellClass(columnMeta)}
                                        style={columnMeta?.cellStyle}
                                        onClick={columnMeta?.interactive || columnMeta?.sticky === 'right' ? stopRowClick : undefined}
                                    >
                                        {columnMeta?.sticky === 'left'
                                            ? <div className={classes.pinnedContent}>{content}</div>
                                            : content}
                                    </MantineTable.Td>
                                );
                            })}
                        </MantineTable.Tr>
                    ))}
                </MantineTable.Tbody>
                {showFooter && (
                    <MantineTable.Tfoot>
                        {table.getFooterGroups().map((footerGroup) => (
                            <MantineTable.Tr key={footerGroup.id}>
                                {footerGroup.headers.map((footer) => {
                                    const columnMeta = footer.column.columnDef.meta as TanStackTableColumnMeta | undefined;

                                    return (
                                        <MantineTable.Td key={footer.id} className={cellClass(columnMeta)}>
                                            {footer.isPlaceholder ? null : renderDefinition(footer.column.columnDef.footer, footer.getContext())}
                                        </MantineTable.Td>
                                    );
                                })}
                            </MantineTable.Tr>
                        ))}
                    </MantineTable.Tfoot>
                )}
            </MantineTable>
        </MantineTable.ScrollContainer>
    );

    const columnToggle = enableColumnVisibility && (
        <ColumnVisibilityToggle table={table} defaultColumnVisibility={defaultColumnVisibility}/>
    );

    return (
        <div className={classes.tableWrapper}>
            {columnToggle && columnToggleSlot === undefined && (
                <div className={classes.toolbar}>{columnToggle}</div>
            )}
            {columnToggle && columnToggleSlot && createPortal(columnToggle, columnToggleSlot)}
            {noCard ? tableContent : <div className={classes.card}>{tableContent}</div>}
        </div>
    );
}
