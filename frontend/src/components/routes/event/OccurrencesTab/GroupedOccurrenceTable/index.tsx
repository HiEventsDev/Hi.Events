import {ReactNode, CSSProperties, useMemo, useCallback} from "react";
import {Table as MantineTable, Checkbox} from "@mantine/core";
import {t} from "@lingui/macro";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {EventOccurrence} from "../../../../../types.ts";
import {Card} from "../../../../common/Card";
import classes from "./GroupedOccurrenceTable.module.scss";

dayjs.extend(utc);
dayjs.extend(timezone);

export interface GroupedTableColumn {
    id: string;
    header: string;
    render: (occ: EventOccurrence) => ReactNode;
    headerStyle?: CSSProperties;
    sticky?: 'right';
}

interface DateGroup {
    dateKey: string;
    label: string;
    occurrences: EventOccurrence[];
}

interface GroupedOccurrenceTableProps {
    occurrences: EventOccurrence[];
    columns: GroupedTableColumn[];
    eventTimezone: string;
    selectedIds: Set<number>;
    onSelectionChange: (ids: Set<number>) => void;
    rowStyle?: (occ: EventOccurrence) => CSSProperties | undefined;
}

const formatDateHeader = (dateKey: string): string => {
    const date = dayjs(dateKey);
    const today = dayjs().startOf('day');
    const tomorrow = today.add(1, 'day');

    if (date.isSame(today, 'day')) {
        return t`Today` + ' — ' + date.format('dddd, MMMM D, YYYY');
    }
    if (date.isSame(tomorrow, 'day')) {
        return t`Tomorrow` + ' — ' + date.format('dddd, MMMM D, YYYY');
    }
    return date.format('dddd, MMMM D, YYYY');
};

export const GroupedOccurrenceTable = ({
    occurrences,
    columns,
    eventTimezone,
    selectedIds,
    onSelectionChange,
    rowStyle,
}: GroupedOccurrenceTableProps) => {
    const groups: DateGroup[] = useMemo(() => {
        const map = new Map<string, EventOccurrence[]>();
        occurrences.forEach(occ => {
            const dateKey = dayjs.utc(occ.start_date).tz(eventTimezone).format('YYYY-MM-DD');
            if (!map.has(dateKey)) map.set(dateKey, []);
            map.get(dateKey)!.push(occ);
        });
        return Array.from(map.entries()).map(([dateKey, occs]) => ({
            dateKey,
            label: formatDateHeader(dateKey),
            occurrences: occs,
        }));
    }, [occurrences, eventTimezone]);

    const allIds = useMemo(
        () => occurrences.map(o => o.id as number),
        [occurrences]
    );

    const allSelected = allIds.length > 0 && allIds.every(id => selectedIds.has(id));
    const someSelected = allIds.some(id => selectedIds.has(id)) && !allSelected;

    const toggleAll = useCallback(() => {
        if (allSelected) {
            onSelectionChange(new Set());
        } else {
            onSelectionChange(new Set(allIds));
        }
    }, [allSelected, allIds, onSelectionChange]);

    const toggleGroup = useCallback((groupOccs: EventOccurrence[]) => {
        const groupIds = groupOccs.map(o => o.id as number);
        const allGroupSelected = groupIds.every(id => selectedIds.has(id));
        const next = new Set(selectedIds);
        if (allGroupSelected) {
            groupIds.forEach(id => next.delete(id));
        } else {
            groupIds.forEach(id => next.add(id));
        }
        onSelectionChange(next);
    }, [selectedIds, onSelectionChange]);

    const toggleRow = useCallback((id: number) => {
        const next = new Set(selectedIds);
        if (next.has(id)) {
            next.delete(id);
        } else {
            next.add(id);
        }
        onSelectionChange(next);
    }, [selectedIds, onSelectionChange]);

    const totalColumns = columns.length + 1; // +1 for checkbox

    return (
        <div className={classes.tableWrapper}>
            <Card className={classes.card}>
                <MantineTable.ScrollContainer minWidth={200} scrollAreaProps={{type: 'hover'}}>
                    <MantineTable className={classes.table}>
                        <MantineTable.Thead className={classes.tableHead}>
                            <MantineTable.Tr>
                                <MantineTable.Th style={{width: 40, paddingLeft: 20}}>
                                    <Checkbox
                                        size="xs"
                                        checked={allSelected}
                                        indeterminate={someSelected}
                                        onChange={toggleAll}
                                        aria-label={t`Select all`}
                                    />
                                </MantineTable.Th>
                                {columns.map(col => (
                                    <MantineTable.Th
                                        key={col.id}
                                        className={col.sticky === 'right' ? classes.stickyRight : undefined}
                                        style={col.headerStyle}
                                    >
                                        {col.header}
                                    </MantineTable.Th>
                                ))}
                            </MantineTable.Tr>
                        </MantineTable.Thead>
                        <MantineTable.Tbody>
                            {groups.map(group => {
                                const groupIds = group.occurrences.map(o => o.id as number);
                                const allGroupSelected = groupIds.every(id => selectedIds.has(id));
                                const someGroupSelected = groupIds.some(id => selectedIds.has(id)) && !allGroupSelected;

                                return [
                                    <MantineTable.Tr key={`header-${group.dateKey}`} className={classes.dateHeaderRow}>
                                        <MantineTable.Td colSpan={totalColumns}>
                                            <div className={classes.dateHeaderContent}>
                                                <Checkbox
                                                    size="xs"
                                                    className={classes.dateHeaderCheckbox}
                                                    checked={allGroupSelected}
                                                    indeterminate={someGroupSelected}
                                                    onChange={() => toggleGroup(group.occurrences)}
                                                    aria-label={t`Select all on ${group.label}`}
                                                />
                                                <span className={classes.dateHeaderLabel}>{group.label}</span>
                                                {group.occurrences.length > 1 && (
                                                    <span className={classes.dateHeaderBadge}>
                                                        {group.occurrences.length}
                                                    </span>
                                                )}
                                            </div>
                                        </MantineTable.Td>
                                    </MantineTable.Tr>,
                                    ...group.occurrences.map(occ => (
                                        <MantineTable.Tr
                                            key={occ.id}
                                            style={rowStyle?.(occ)}
                                        >
                                            <MantineTable.Td style={{width: 40, paddingLeft: 20}}>
                                                <div className={classes.rowCheckbox}>
                                                    <Checkbox
                                                        size="xs"
                                                        checked={selectedIds.has(occ.id as number)}
                                                        onChange={() => toggleRow(occ.id as number)}
                                                        aria-label={t`Select date`}
                                                    />
                                                </div>
                                            </MantineTable.Td>
                                            {columns.map(col => (
                                                <MantineTable.Td
                                                    key={col.id}
                                                    className={col.sticky === 'right' ? classes.stickyRight : undefined}
                                                >
                                                    {col.render(occ)}
                                                </MantineTable.Td>
                                            ))}
                                        </MantineTable.Tr>
                                    )),
                                ];
                            })}
                        </MantineTable.Tbody>
                    </MantineTable>
                </MantineTable.ScrollContainer>
            </Card>
        </div>
    );
};
