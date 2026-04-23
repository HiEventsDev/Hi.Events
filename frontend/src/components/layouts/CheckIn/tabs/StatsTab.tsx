import {useEffect, useMemo, useState} from "react";
import {t, Trans} from "@lingui/macro";
import {IconBolt, IconCheck, IconClock, IconTicket, IconUsers} from "@tabler/icons-react";
import {Progress} from "@mantine/core";
import {IdParam} from "../../../../types.ts";
import {useGetCheckInListStatsPublic} from "../../../../queries/useGetCheckInListStatsPublic.ts";
import classes from "./StatsTab.module.scss";

interface StatsTabProps {
    checkInListShortId: IdParam;
    enabled: boolean;
    /**
     * When the staff has narrowed an unscoped check-in list via the filter pill,
     * pass the selected occurrence id so the stats endpoint returns counts for
     * that session instead of the whole list.
     */
    eventOccurrenceId?: number | null;
}

const formatTime = (iso?: string) => {
    if (!iso) return "";
    const normalized = iso.includes("T") ? iso : iso.replace(" ", "T") + "Z";
    const d = new Date(normalized);
    if (Number.isNaN(d.getTime())) return "";
    return d.toLocaleTimeString([], {hour: "numeric", minute: "2-digit"});
};

const THROUGHPUT_WINDOW_MINUTES = 5;

const parseCheckInTime = (iso: string): number | null => {
    if (!iso) return null;
    const normalized = iso.includes("T") ? iso : iso.replace(" ", "T") + "Z";
    const t = new Date(normalized).getTime();
    return Number.isNaN(t) ? null : t;
};

export const StatsTab = ({checkInListShortId, enabled, eventOccurrenceId}: StatsTabProps) => {
    const statsQuery = useGetCheckInListStatsPublic(checkInListShortId, enabled, eventOccurrenceId);
    const stats = statsQuery.data?.data;
    const [now, setNow] = useState(() => Date.now());

    // Recompute throughput every 15s so the window stays accurate without a refetch.
    useEffect(() => {
        const id = setInterval(() => setNow(Date.now()), 15_000);
        return () => clearInterval(id);
    }, []);

    const total = stats?.total_attendees ?? 0;
    const checkedIn = stats?.checked_in_attendees ?? 0;
    const remaining = Math.max(0, total - checkedIn);
    const percent = total > 0 ? Math.round((checkedIn / total) * 100) : 0;

    const throughput = useMemo(() => {
        if (!stats?.recent_check_ins?.length) {
            return {inWindow: 0, perMinute: 0, windowMinutes: THROUGHPUT_WINDOW_MINUTES};
        }
        const windowMs = THROUGHPUT_WINDOW_MINUTES * 60_000;
        const cutoff = now - windowMs;
        const inWindow = stats.recent_check_ins.filter(c => {
            const ts = parseCheckInTime(c.checked_in_at);
            return ts !== null && ts >= cutoff;
        }).length;
        return {
            inWindow,
            perMinute: +(inWindow / THROUGHPUT_WINDOW_MINUTES).toFixed(1),
            windowMinutes: THROUGHPUT_WINDOW_MINUTES,
        };
    }, [stats?.recent_check_ins, now]);

    return (
        <div className={classes.wrap}>
            <div className={classes.hero}>
                <div className={classes.heroCount}>
                    <span className={classes.heroBig}>{checkedIn}</span>
                    <span className={classes.heroOf}>/ {total}</span>
                </div>
                <div className={classes.heroLabel}>
                    <Trans>attendees checked in</Trans>
                </div>
                <Progress
                    value={percent}
                    color="teal"
                    size="lg"
                    radius="xl"
                    className={classes.heroProgress}
                />
                <div className={classes.heroPercent}>
                    <strong>{percent}%</strong> <Trans>complete</Trans>
                </div>
            </div>

            <div className={classes.grid}>
                <div className={classes.card}>
                    <div className={classes.cardIcon}><IconCheck size={16}/></div>
                    <div className={classes.cardLabel}>{t`Checked in`}</div>
                    <div className={classes.cardValue}>{checkedIn}</div>
                </div>
                <div className={classes.card}>
                    <div className={`${classes.cardIcon} ${classes.cardIconWarn}`}><IconClock size={16}/></div>
                    <div className={classes.cardLabel}>{t`Remaining`}</div>
                    <div className={classes.cardValue}>{remaining}</div>
                </div>
                <div className={classes.card}>
                    <div className={`${classes.cardIcon} ${classes.cardIconMuted}`}><IconUsers size={16}/></div>
                    <div className={classes.cardLabel}>{t`Total`}</div>
                    <div className={classes.cardValue}>{total}</div>
                </div>
            </div>

            <div className={classes.throughput}>
                <div className={classes.throughputIcon}><IconBolt size={18}/></div>
                <div className={classes.throughputBody}>
                    <div className={classes.throughputLabel}>{t`Throughput`}</div>
                    <div className={classes.throughputValue}>
                        <strong>{throughput.inWindow}</strong>{" "}
                        <span className={classes.throughputUnit}>
                            <Trans>in last {throughput.windowMinutes} min</Trans>
                        </span>
                    </div>
                </div>
                <div className={classes.throughputRate}>
                    <strong>{throughput.perMinute}</strong>
                    <span>{t`per min`}</span>
                </div>
            </div>

            {stats && stats.per_product.length > 1 && (
                <>
                    <div className={classes.sectionLabel}>{t`By ticket type`}</div>
                    <div className={classes.productList}>
                        {stats.per_product.map(p => {
                            const pct = p.total_attendees > 0
                                ? Math.round((p.checked_in_attendees / p.total_attendees) * 100)
                                : 0;
                            return (
                                <div className={classes.productRow} key={p.product_id}>
                                    <div className={classes.productTop}>
                                        <div className={classes.productName}>
                                            <IconTicket size={14}/> {p.product_title}
                                        </div>
                                        <div className={classes.productCount}>
                                            {p.checked_in_attendees}<span className={classes.productOf}>/{p.total_attendees}</span>
                                        </div>
                                    </div>
                                    <Progress
                                        value={pct}
                                        color={pct === 100 ? "teal" : "grape"}
                                        size="sm"
                                        radius="xl"
                                    />
                                </div>
                            );
                        })}
                    </div>
                </>
            )}

            <div className={classes.sectionLabel}>{t`Latest check-ins`}</div>
            {!stats || stats.recent_check_ins.length === 0 ? (
                <div className={classes.empty}>
                    <Trans>No check-ins yet</Trans>
                </div>
            ) : (
                <div className={classes.recentList}>
                    {stats.recent_check_ins.map(checkIn => (
                        <div className={classes.recent} key={`${checkIn.attendee_public_id}-${checkIn.checked_in_at}`}>
                            <div className={classes.recentCheck}><IconCheck size={14} stroke={3}/></div>
                            <div className={classes.recentMain}>
                                <div className={classes.recentName}>
                                    {checkIn.first_name} {checkIn.last_name}
                                </div>
                                <div className={classes.recentCode}>
                                    {checkIn.attendee_public_id}
                                    {checkIn.product_title && (
                                        <> · {checkIn.product_title}</>
                                    )}
                                </div>
                            </div>
                            <div className={classes.recentTime}>{formatTime(checkIn.checked_in_at)}</div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};
