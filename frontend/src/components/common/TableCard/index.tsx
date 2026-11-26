import {ReactNode} from "react";
import {t} from "@lingui/macro";
import {Pagination, Skeleton} from "@mantine/core";
import {PaginationData} from "../../../types.ts";
import classes from "./TableCard.module.scss";

export const TableCard = ({children}: { children: ReactNode }) => (
    <section className={classes.card}>{children}</section>
);

interface TableCardFooterProps {
    pagination: PaginationData | undefined;
    page: number | undefined;
    onPageChange: (page: number) => void;
}

export const TableCardFooter = ({pagination, page, onPageChange}: TableCardFooterProps) => {
    if (!pagination || pagination.total === 0) {
        return null;
    }

    return (
        <footer className={classes.footer}>
            <span className={classes.range}>{t`Showing ${(pagination.from ?? 0).toLocaleString()}–${(pagination.to ?? 0).toLocaleString()} of ${pagination.total.toLocaleString()}`}</span>
            {pagination.last_page > 1 && (
                <Pagination
                    size="sm"
                    siblings={1}
                    boundaries={1}
                    value={page ?? pagination.current_page}
                    total={pagination.last_page}
                    onChange={onPageChange}
                />
            )}
        </footer>
    );
};

const SKELETON_WIDTHS = [38, 24, 14, 18, 12];

export const TableCardSkeleton = ({rows = 10}: { rows?: number }) => (
    <div aria-hidden>
        {Array.from({length: rows}, (_, row) => (
            <div key={row} className={classes.skeletonRow}>
                {SKELETON_WIDTHS.map((width, column) => (
                    <Skeleton key={column} height={10} radius="xl" width={`${width - (row + column) % 3 * 3}%`}/>
                ))}
            </div>
        ))}
    </div>
);
