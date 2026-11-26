import {ReactNode} from "react";
import {t} from "@lingui/macro";
import {Button, Checkbox, Menu} from "@mantine/core";
import {IconArrowDown, IconArrowsSort, IconArrowUp} from "@tabler/icons-react";
import {SortDirection} from "../../../../../components/common/TanStackTable/sorting.tsx";
import classes from "./Contacts.module.scss";

export interface SortOption {
    field: string;
    label: string;
    firstDirection: SortDirection;
}

interface ContactStackProps<TRow> {
    rows: TRow[];
    getRowId: (row: TRow) => string;
    getRowLabel: (row: TRow) => string;
    renderRow: (row: TRow) => ReactNode;
    onOpen: (row: TRow) => void;
    selectedIds: ReadonlySet<string>;
    onSelectionChange: (selectedIds: Set<string>) => void;
    sortOptions: SortOption[];
    sortBy: string;
    sortDirection: SortDirection;
    onSort: (sortBy: string, sortDirection: SortDirection) => void;
}

const DirectionIcon = ({direction}: { direction: SortDirection }) => direction === 'asc'
    ? <IconArrowUp size={14} stroke={1.8} aria-hidden/>
    : <IconArrowDown size={14} stroke={1.8} aria-hidden/>;

export function ContactStack<TRow>({
    rows,
    getRowId,
    getRowLabel,
    renderRow,
    onOpen,
    selectedIds,
    onSelectionChange,
    sortOptions,
    sortBy,
    sortDirection,
    onSort,
}: ContactStackProps<TRow>) {
    const rowIds = rows.map(getRowId);
    const selectedOnPage = rowIds.filter(id => selectedIds.has(id)).length;
    const activeSort = sortOptions.find(option => option.field === sortBy);

    const togglePage = () => {
        const next = new Set(selectedIds);
        const selectAll = selectedOnPage < rowIds.length;
        rowIds.forEach(id => selectAll ? next.add(id) : next.delete(id));
        onSelectionChange(next);
    };

    const toggleRow = (id: string) => {
        const next = new Set(selectedIds);
        if (!next.delete(id)) {
            next.add(id);
        }
        onSelectionChange(next);
    };

    const sort = (option: SortOption) => onSort(
        option.field,
        option.field === sortBy ? (sortDirection === 'asc' ? 'desc' : 'asc') : option.firstDirection,
    );

    return (
        <div className={classes.stack}>
            <div className={classes.stackHeader}>
                <label className={classes.stackSelectAll}>
                    <span className={classes.stackCheck}>
                        <Checkbox
                            size="sm"
                            aria-label={t`Select all rows on this page`}
                            checked={rowIds.length > 0 && selectedOnPage === rowIds.length}
                            indeterminate={selectedOnPage > 0 && selectedOnPage < rowIds.length}
                            onChange={togglePage}
                        />
                    </span>
                    <span aria-hidden>{t`Select all`}</span>
                </label>
                <Menu position="bottom-end" shadow="md" withinPortal>
                    <Menu.Target>
                        <Button
                            className={classes.stackSort}
                            variant="subtle"
                            color="gray"
                            size="compact-sm"
                            leftSection={<IconArrowsSort size={15}/>}
                            rightSection={activeSort ? <DirectionIcon direction={sortDirection}/> : undefined}
                        >
                            {activeSort ? activeSort.label : t`Sort`}
                        </Button>
                    </Menu.Target>
                    <Menu.Dropdown>
                        <Menu.Label>{t`Sort by`}</Menu.Label>
                        {sortOptions.map(option => (
                            <Menu.Item
                                key={option.field}
                                onClick={() => sort(option)}
                                rightSection={option.field === sortBy ? <DirectionIcon direction={sortDirection}/> : undefined}
                                aria-current={option.field === sortBy || undefined}
                            >
                                {option.label}
                            </Menu.Item>
                        ))}
                    </Menu.Dropdown>
                </Menu>
            </div>
            <ul className={classes.stackRows}>
                {rows.map(row => {
                    const id = getRowId(row);
                    const isSelected = selectedIds.has(id);

                    return (
                        <li
                            key={id}
                            className={classes.stackRow}
                            data-selected={isSelected || undefined}
                            data-testid="audience-contact-row"
                            onClick={() => onOpen(row)}
                        >
                            <span
                                className={classes.stackCheck}
                                onClick={event => {
                                    event.stopPropagation();
                                    toggleRow(id);
                                }}
                            >
                                <Checkbox
                                    size="sm"
                                    aria-label={getRowLabel(row)}
                                    checked={isSelected}
                                    onChange={() => undefined}
                                    onClick={event => {
                                        event.stopPropagation();
                                        toggleRow(id);
                                    }}
                                />
                            </span>
                            {renderRow(row)}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
