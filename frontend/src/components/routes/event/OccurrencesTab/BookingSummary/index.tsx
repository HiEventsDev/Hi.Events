import {t} from "@lingui/macro";
import {Popover, Progress, UnstyledButton} from "@mantine/core";
import {IconChevronDown} from "@tabler/icons-react";
import {OccurrenceTierAllocation, ProductQuantityAppliesTo} from "../../../../../types.ts";
import {cappedAllocationTotal} from "../bookingLimits.ts";
import classes from "./BookingSummary.module.scss";

export interface BookingFigures {
    capacity: number | null;
    booked: number;
    allocations: OccurrenceTierAllocation[];
}

const allocationLimit = (allocations: OccurrenceTierAllocation[]): number | null => {
    if (allocations.length === 0 || allocations.some((allocation) => allocation.quantity === null)) {
        return null;
    }
    return cappedAllocationTotal(allocations);
};

const sellableLimit = ({capacity, allocations}: Pick<BookingFigures, 'capacity' | 'allocations'>): number | null => {
    const limits = [capacity, allocationLimit(allocations)].filter((value): value is number => value !== null);
    return limits.length === 0 ? null : Math.min(...limits);
};

const tierValue = (allocation: OccurrenceTierAllocation): string => {
    if (allocation.applies_to === ProductQuantityAppliesTo.Event) {
        return t`All dates · shared`;
    }
    return allocation.quantity === null ? t`Unlimited` : String(allocation.quantity);
};

const allocationValue = (allocations: OccurrenceTierAllocation[]): string => {
    const cappedTotal = cappedAllocationTotal(allocations);
    const hasUnlimited = allocations.some((allocation) => allocation.quantity === null && allocation.applies_to !== ProductQuantityAppliesTo.Event);
    const hasShared = allocations.some((allocation) => allocation.applies_to === ProductQuantityAppliesTo.Event);
    if (cappedTotal === null) {
        return t`Unlimited`;
    }
    if (hasUnlimited) {
        return t`${cappedTotal} + unlimited`;
    }
    if (hasShared) {
        return t`${cappedTotal} + shared`;
    }
    return String(cappedTotal);
};

export const BookingBreakdown = ({capacity, booked, allocations}: BookingFigures) => {
    const allocationTotal = allocationLimit(allocations);
    const sellable = sellableLimit({capacity, allocations});
    const limitedBy = (() => {
        if (capacity === null || allocationTotal === null || capacity === allocationTotal) {
            return null;
        }
        return capacity < allocationTotal ? t`Limited by capacity` : t`Limited by ticket allocation`;
    })();

    return (
        <div className={classes.breakdown} data-testid="occurrence-booking-breakdown">
            <div className={classes.breakdownTitle}>{t`Booking limit for this date`}</div>
            <div className={classes.row} data-testid="occurrence-booking-row-capacity">
                <span>{t`Capacity`}</span>
                <span className={classes.value}>{capacity === null ? t`Unlimited` : capacity}</span>
            </div>
            <div className={classes.row} data-testid="occurrence-booking-row-allocation">
                <span>{t`Ticket allocation`}</span>
                <span className={classes.value}>{allocationValue(allocations)}</span>
            </div>
            {allocations.map((allocation) => (
                <div key={String(allocation.product_price_id)} className={classes.tierRow} data-testid="occurrence-booking-row-tier">
                    <span>{allocation.price_label ?? allocation.product_title}</span>
                    <span className={classes.value}>{tierValue(allocation)}</span>
                </div>
            ))}
            <div className={classes.totalRow} data-testid="occurrence-booking-row-sellable">
                <span>{t`Sellable`}</span>
                <span className={classes.value}>{sellable === null ? t`Unlimited` : sellable}</span>
            </div>
            <div className={classes.row} data-testid="occurrence-booking-row-booked">
                <span>{t`Booked`}</span>
                <span className={classes.value}>{booked}</span>
            </div>
            {limitedBy && <div className={classes.note} data-testid="occurrence-booking-limited-by">{limitedBy}</div>}
        </div>
    );
};

export const BookingSummary = (figures: BookingFigures) => {
    const sellable = sellableLimit(figures);
    const pct = sellable ? Math.min(100, Math.round((figures.booked / sellable) * 100)) : 0;
    const booked = figures.booked;

    return (
        <div className={classes.summary}>
            <div className={classes.summaryRow}>
                <span className={classes.summaryText} data-testid="occurrence-booking-summary">
                    {sellable === null ? t`${booked} booked · no date limit` : t`${booked} of ${sellable} booked`}
                </span>
                <Popover position="bottom-end" width={320} shadow="md" withArrow>
                    <Popover.Target>
                        <UnstyledButton className={classes.detailsButton} data-testid="occurrence-booking-details-button">
                            {t`Details`}
                            <IconChevronDown size={14}/>
                        </UnstyledButton>
                    </Popover.Target>
                    <Popover.Dropdown p={0}>
                        <BookingBreakdown {...figures}/>
                    </Popover.Dropdown>
                </Popover>
            </div>
            {sellable !== null && (
                <Progress
                    value={pct}
                    size={4}
                    radius="xl"
                    color={pct >= 90 ? 'red' : pct >= 70 ? 'orange' : 'blue'}
                    className={classes.progress}
                />
            )}
        </div>
    );
};
