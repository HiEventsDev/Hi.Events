import {Table, UnstyledButton} from "@mantine/core";
import {IconSortAscending, IconSortDescending} from "@tabler/icons-react";

interface SortableThProps {
    label: string;
    field: string;
    sortBy: string;
    sortDir: string;
    onSort: (field: string) => void;
    width?: number | string;
}

export const SortableTh = ({label, field, sortBy, sortDir, onSort, width}: SortableThProps) => {
    const isActive = sortBy === field;
    const Icon = isActive && sortDir === 'asc' ? IconSortAscending : IconSortDescending;
    return (
        <Table.Th style={width !== undefined ? {width} : undefined}>
            <UnstyledButton
                onClick={() => onSort(field)}
                style={{display: 'flex', alignItems: 'center', gap: 4, fontWeight: 700}}
            >
                {label}
                {isActive && <Icon size={14} style={{opacity: 0.6}}/>}
            </UnstyledButton>
        </Table.Th>
    );
};
