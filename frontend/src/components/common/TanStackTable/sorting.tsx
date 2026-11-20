import {t} from '@lingui/macro';
import {UnstyledButton, VisuallyHidden} from '@mantine/core';
import {IconArrowDown, IconArrowUp, IconSelector} from '@tabler/icons-react';
import {PaginationData, QueryFilters} from '../../../types.ts';
import classes from './cells.module.scss';

export type SortDirection = 'asc' | 'desc';

export interface TableSort {
    sortBy: string;
    sortDirection: SortDirection;
    onSort: (sortBy: string, sortDirection: SortDirection) => void;
    allowedFields?: string[];
}

interface SortHeaderProps {
    label: string;
    field: string;
    firstDirection?: SortDirection;
    sort: TableSort;
}

export const SortHeader = ({label, field, firstDirection = 'desc', sort}: SortHeaderProps) => {
    const isActive = sort.sortBy === field;
    const Icon = !isActive ? IconSelector : sort.sortDirection === 'asc' ? IconArrowUp : IconArrowDown;
    const nextDirection = isActive ? (sort.sortDirection === 'asc' ? 'desc' : 'asc') : firstDirection;

    return (
        <UnstyledButton
            className={classes.sortHeader}
            data-active={isActive || undefined}
            onClick={() => sort.onSort(field, nextDirection)}
        >
            {label}
            <Icon size={14} stroke={1.8} aria-hidden/>
            {isActive && (
                <VisuallyHidden>{sort.sortDirection === 'asc' ? t`Sorted ascending` : t`Sorted descending`}</VisuallyHidden>
            )}
        </UnstyledButton>
    );
};

export const sortableHeader = (label: string, field: string, sort: TableSort | undefined, firstDirection: SortDirection = 'desc') => {
    const isSortable = !!sort && (!sort.allowedFields || sort.allowedFields.includes(field));

    return isSortable
        ? {header: () => <SortHeader label={label} field={field} firstDirection={firstDirection} sort={sort}/>}
        : {header: label};
};

export const tableSortFromQuery = (
    searchParams: Partial<QueryFilters>,
    setSearchParams: (updates: Partial<QueryFilters>) => void,
    pagination: PaginationData | undefined,
): TableSort | undefined => {
    if (!pagination?.allowed_sorts) {
        return undefined;
    }

    return {
        sortBy: searchParams.sortBy || pagination.default_sort,
        sortDirection: (searchParams.sortDirection || pagination.default_sort_direction) as SortDirection,
        onSort: (sortBy, sortDirection) => setSearchParams({sortBy, sortDirection, pageNumber: 1}),
        allowedFields: Object.keys(pagination.allowed_sorts),
    };
};
