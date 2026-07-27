import {useMemo, useState} from "react";
import {t, Trans} from "@lingui/macro";
import {IconCalendarEvent, IconCheck, IconSearch, IconTicket, IconX} from "@tabler/icons-react";
import {Button, Loader} from "@mantine/core";
import {Attendee, EventType} from "../../../../types.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import classes from "./SearchTab.module.scss";

type FilterKey = "all" | "pending" | "checked_in" | "awaiting_payment";

interface SearchTabProps {
    attendees: Attendee[] | undefined;
    products: { id: number; title: string }[] | undefined;
    searchQuery: string;
    onSearchChange: (value: string) => void;
    onCheckInToggle: (attendee: Attendee) => void;
    onOpenDetail: (attendeePublicId: string) => void;
    isLoading: boolean;
    isCheckInPending: boolean;
    isDeletePending: boolean;
    allowOrdersAwaitingOfflinePaymentToCheckIn: boolean;
    eventType?: EventType;
    timezone?: string;
    showRowOccurrences?: boolean;
}

const getInitials = (attendee: Attendee) =>
    `${(attendee.first_name || "").charAt(0)}${(attendee.last_name || "").charAt(0)}`.toUpperCase() || "?";

export const SearchTab = ({
                              attendees,
                              products,
                              searchQuery,
                              onSearchChange,
                              onCheckInToggle,
                              onOpenDetail,
                              isLoading,
                              isCheckInPending,
                              isDeletePending,
                              allowOrdersAwaitingOfflinePaymentToCheckIn,
                              eventType,
                              timezone,
                              showRowOccurrences,
                          }: SearchTabProps) => {
    const showOccurrence = showRowOccurrences && eventType === EventType.RECURRING && !!timezone;
    const [filter, setFilter] = useState<FilterKey>("all");

    const stats = useMemo(() => {
        if (!attendees) return {total: 0, checkedIn: 0, pending: 0, awaiting: 0};
        return attendees.reduce(
            (acc, a) => {
                acc.total += 1;
                if (a.check_in) acc.checkedIn += 1;
                else if (a.status === "AWAITING_PAYMENT") acc.awaiting += 1;
                else if (a.status === "ACTIVE") acc.pending += 1;
                return acc;
            },
            {total: 0, checkedIn: 0, pending: 0, awaiting: 0}
        );
    }, [attendees]);

    const filtered = useMemo(() => {
        if (!attendees) return [];
        return attendees.filter(a => {
            if (filter === "checked_in") return !!a.check_in;
            if (filter === "pending") return !a.check_in && a.status === "ACTIVE";
            if (filter === "awaiting_payment") return a.status === "AWAITING_PAYMENT";
            return true;
        });
    }, [attendees, filter]);

    const getButtonColor = (attendee: Attendee) => {
        if (attendee.check_in || attendee.status === "CANCELLED") return "red";
        if (attendee.status === "AWAITING_PAYMENT" && !allowOrdersAwaitingOfflinePaymentToCheckIn) return "gray";
        return "teal";
    };

    const checkInLabel = (attendee: Attendee) => {
        if (!allowOrdersAwaitingOfflinePaymentToCheckIn && attendee.status === "AWAITING_PAYMENT") return t`Can't check in`;
        if (attendee.status === "CANCELLED") return t`Cancelled`;
        if (attendee.check_in) return t`Check out`;
        return t`Check in`;
    };

    return (
        <div className={classes.wrap}>
            <div className={classes.searchField}>
                <IconSearch size={20} stroke={1.8}/>
                <input
                    type="text"
                    value={searchQuery}
                    onChange={e => onSearchChange(e.target.value)}
                    placeholder={t`Search by name, order #, ticket # or email`}
                    aria-label={t`Search attendees`}
                />
                {searchQuery && (
                    <button
                        type="button"
                        className={classes.clearBtn}
                        aria-label={t`Clear search`}
                        onClick={() => onSearchChange("")}
                    >
                        <IconX size={16}/>
                    </button>
                )}
            </div>

            <div className={classes.statRow}>
                <div className={classes.statCell}>
                    <div className={classes.statValue}>{stats.checkedIn}<span className={classes.statOf}>/{stats.total}</span></div>
                    <div className={classes.statLabel}>{t`Checked in`}</div>
                </div>
                <div className={classes.statCell}>
                    <div className={classes.statValue}>{stats.pending}</div>
                    <div className={classes.statLabel}>{t`Remaining`}</div>
                </div>
                {stats.awaiting > 0 && (
                    <div className={classes.statCell}>
                        <div className={classes.statValue}>{stats.awaiting}</div>
                        <div className={classes.statLabel}>{t`Awaiting pay`}</div>
                    </div>
                )}
            </div>

            <div className={classes.chipRow} role="tablist" aria-label={t`Filter attendees`}>
                <button
                    type="button"
                    role="tab"
                    aria-selected={filter === "all"}
                    className={`${classes.chip} ${filter === "all" ? classes.chipActive : ""}`}
                    onClick={() => setFilter("all")}
                >
                    {t`All`} <span className={classes.chipCount}>{stats.total}</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected={filter === "pending"}
                    className={`${classes.chip} ${filter === "pending" ? classes.chipActive : ""}`}
                    onClick={() => setFilter("pending")}
                >
                    {t`Remaining`} <span className={classes.chipCount}>{stats.pending}</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected={filter === "checked_in"}
                    className={`${classes.chip} ${filter === "checked_in" ? classes.chipActive : ""}`}
                    onClick={() => setFilter("checked_in")}
                >
                    {t`Checked in`} <span className={classes.chipCount}>{stats.checkedIn}</span>
                </button>
                {stats.awaiting > 0 && (
                    <button
                        type="button"
                        role="tab"
                        aria-selected={filter === "awaiting_payment"}
                        className={`${classes.chip} ${filter === "awaiting_payment" ? classes.chipActive : ""}`}
                        onClick={() => setFilter("awaiting_payment")}
                    >
                        {t`Awaiting`} <span className={classes.chipCount}>{stats.awaiting}</span>
                    </button>
                )}
            </div>

            <div className={classes.list}>
                {isLoading || !attendees || !products ? (
                    <div className={classes.loader}>
                        <Loader size={32}/>
                    </div>
                ) : filtered.length === 0 ? (
                    <div className={classes.empty}>
                        <p><Trans>No attendees to show</Trans></p>
                        {searchQuery && (
                            <p className={classes.emptySub}>
                                <Trans>Try a different search term or filter</Trans>
                            </p>
                        )}
                    </div>
                ) : (
                    filtered.map(attendee => {
                        const product = products.find(p => p.id === attendee.product_id);
                        const isCheckedIn = !!attendee.check_in;
                        const isAwaiting = attendee.status === "AWAITING_PAYMENT";
                        const isCancelled = attendee.status === "CANCELLED";
                        return (
                            <button
                                type="button"
                                key={attendee.public_id}
                                className={`${classes.row} ${isCheckedIn ? classes.rowDone : ""} ${isCancelled ? classes.rowCancelled : ""}`}
                                onClick={() => onOpenDetail(attendee.public_id)}
                                aria-label={t`View details for ${attendee.first_name} ${attendee.last_name}`}
                            >
                                <div className={`${classes.avatar} ${isCheckedIn ? classes.avatarDone : ""}`}>
                                    {isCheckedIn ? <IconCheck size={20} stroke={3}/> : getInitials(attendee)}
                                </div>
                                <div className={classes.rowMain}>
                                    <div className={classes.rowName}>
                                        {attendee.first_name} {attendee.last_name}
                                    </div>
                                    <div className={classes.rowMeta}>
                                        <span className={classes.rowCode}>{attendee.public_id}</span>
                                        {product && (
                                            <>
                                                <span className={classes.dot}>•</span>
                                                <span className={classes.rowProduct}>
                                                    <IconTicket size={12}/> {product.title}
                                                </span>
                                            </>
                                        )}
                                    </div>
                                    {showOccurrence && attendee.event_occurrence && (
                                        <div className={classes.rowOccurrence}>
                                            <IconCalendarEvent size={12}/>
                                            <span>
                                                {formatDateWithLocale(attendee.event_occurrence.start_date, 'shortDate', timezone!)}
                                                {' · '}
                                                {formatDateWithLocale(attendee.event_occurrence.start_date, 'timeOnly', timezone!)}
                                                {attendee.event_occurrence.label ? ` · ${attendee.event_occurrence.label}` : ''}
                                            </span>
                                        </div>
                                    )}
                                    {(isAwaiting || isCancelled) && (
                                        <div className={classes.rowTags}>
                                            {isAwaiting && <span className={classes.tagWarn}>{t`Awaiting payment`}</span>}
                                            {isCancelled && <span className={classes.tagDanger}>{t`Cancelled`}</span>}
                                        </div>
                                    )}
                                </div>
                                <Button
                                    size="sm"
                                    color={getButtonColor(attendee)}
                                    disabled={isCheckInPending || isDeletePending || isCancelled}
                                    loading={isCheckInPending || isDeletePending}
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onCheckInToggle(attendee);
                                    }}
                                    className={classes.rowAction}
                                >
                                    {checkInLabel(attendee)}
                                </Button>
                            </button>
                        );
                    })
                )}
            </div>
        </div>
    );
};
