import {useCallback, useEffect, useMemo, useRef, useState} from "react";
import {Badge, Button, Drawer, Loader} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {
    IconAlertTriangle,
    IconCalendarEvent,
    IconCheck,
    IconClipboardText,
    IconMail,
    IconReceipt2,
    IconTicket,
    IconUser,
    IconX,
} from "@tabler/icons-react";
import {AttendeeDetailPublic, EventType} from "../../../types.ts";
import {useGetCheckInListAttendeeDetailPublic} from "../../../queries/useGetCheckInListAttendeeDetailPublic.ts";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {formatDateWithLocale} from "../../../utilites/dates.ts";
import classes from "./AttendeeDetailSheet.module.scss";

interface Props {
    checkInListShortId: string | undefined;
    attendeePublicId: string | null;
    eventType?: EventType;
    timezone?: string;
    onClose: () => void;
    onCheckInToggle: (detail: AttendeeDetailPublic) => void;
    isActionPending: boolean;
}

const formatDateTime = (iso: string | undefined | null) => {
    if (!iso) return "";
    const normalized = iso.includes("T") ? iso : iso.replace(" ", "T") + "Z";
    const d = new Date(normalized);
    if (Number.isNaN(d.getTime())) return "";
    return d.toLocaleString([], {month: "short", day: "numeric", hour: "numeric", minute: "2-digit"});
};

const answerToString = (answer: string | string[] | null | undefined): string => {
    if (answer == null) return "—";
    if (Array.isArray(answer)) return answer.join(", ");
    return answer;
};

const DRAG_DISMISS_THRESHOLD_PX = 90;

export const AttendeeDetailSheet = ({
                                        checkInListShortId,
                                        attendeePublicId,
                                        eventType,
                                        timezone,
                                        onClose,
                                        onCheckInToggle,
                                        isActionPending,
                                    }: Props) => {
    const open = attendeePublicId !== null;
    const detailQuery = useGetCheckInListAttendeeDetailPublic(checkInListShortId, attendeePublicId);
    const meQuery = useGetMe();
    const isLoggedIn = !!meQuery.data?.id;

    // Drag-to-dismiss: track a downward drag from the handle and close when the user
    // passes the threshold. Smaller deltas snap back via the CSS transition.
    const dragStartRef = useRef<number | null>(null);
    const [dragOffset, setDragOffset] = useState(0);
    const [dragging, setDragging] = useState(false);

    useEffect(() => {
        if (!open) {
            setDragOffset(0);
            setDragging(false);
            dragStartRef.current = null;
        }
    }, [open]);

    const onHandlePointerDown = useCallback((e: React.PointerEvent<HTMLDivElement>) => {
        dragStartRef.current = e.clientY;
        setDragging(true);
        e.currentTarget.setPointerCapture(e.pointerId);
    }, []);

    const onHandlePointerMove = useCallback((e: React.PointerEvent<HTMLDivElement>) => {
        if (dragStartRef.current === null) return;
        // Downward drag only; upward drag is clamped to 0 so the sheet doesn't lift.
        const delta = Math.max(0, e.clientY - dragStartRef.current);
        setDragOffset(delta);
    }, []);

    const onHandlePointerEnd = useCallback((e: React.PointerEvent<HTMLDivElement>) => {
        if (dragStartRef.current === null) return;
        const delta = Math.max(0, e.clientY - dragStartRef.current);
        dragStartRef.current = null;
        setDragging(false);
        try {
            e.currentTarget.releasePointerCapture(e.pointerId);
        } catch {
            // Capture may have already been released; ignore.
        }
        if (delta > DRAG_DISMISS_THRESHOLD_PX) {
            onClose();
        } else {
            setDragOffset(0);
        }
    }, [onClose]);

    const detail = detailQuery.data?.data;
    const currentCheckIn = detail?.check_ins?.[0];
    const isCheckedIn = !!currentCheckIn;

    // The server already filters based on list visibility + staff account. When logged in,
    // the frontend might be loaded before the stats/filter resolve - so we treat the logged-in
    // flag as authoritative for showing sensitive fields.
    const visibility = detail?.visibility;
    const showNotes = isLoggedIn || !!visibility?.notes;
    const showQuestions = isLoggedIn || !!visibility?.question_answers;
    const showOrder = isLoggedIn || !!visibility?.order_details;

    const statusBadge = useMemo(() => {
        if (!detail) return null;
        if (detail.status === "CANCELLED") {
            return <Badge color="red" variant="light" size="sm">{t`Cancelled`}</Badge>;
        }
        if (detail.status === "AWAITING_PAYMENT") {
            return <Badge color="orange" variant="light" size="sm">{t`Awaiting payment`}</Badge>;
        }
        if (isCheckedIn) {
            return <Badge color="teal" variant="light" size="sm">{t`Checked in`}</Badge>;
        }
        return <Badge color="gray" variant="light" size="sm">{t`Not checked in`}</Badge>;
    }, [detail, isCheckedIn]);

    const canToggle = detail && detail.status !== "CANCELLED";

    return (
        <Drawer
            opened={open}
            onClose={onClose}
            position="bottom"
            size={640}
            padding="0"
            withCloseButton={false}
            overlayProps={{backgroundOpacity: 0.45, blur: 2}}
            classNames={{content: classes.drawer, body: classes.body}}
            styles={{
                content: {
                    transform: dragOffset > 0 ? `translateY(${dragOffset}px)` : undefined,
                    transition: dragging ? "none" : undefined,
                },
            }}
        >
            <div
                className={classes.handleHitArea}
                onPointerDown={onHandlePointerDown}
                onPointerMove={onHandlePointerMove}
                onPointerUp={onHandlePointerEnd}
                onPointerCancel={onHandlePointerEnd}
                aria-hidden="true"
            >
                <div className={classes.handle}/>
            </div>

            {detailQuery.isLoading && !detail && (
                <div className={classes.loading}>
                    <Loader size="sm"/>
                </div>
            )}

            {detailQuery.isError && (
                <div className={classes.errorBlock}>
                    <IconAlertTriangle size={20}/>
                    <div>{t`Unable to load attendee details.`}</div>
                </div>
            )}

            {detail && (
                <>
                    <header className={classes.header}>
                        <div className={classes.headerMain}>
                            <div className={classes.avatar} aria-hidden="true">
                                <IconUser size={22}/>
                            </div>
                            <div className={classes.headerText}>
                                <div className={classes.name}>
                                    {detail.first_name} {detail.last_name}
                                </div>
                                <div className={classes.meta}>
                                    <span className={classes.code}>{detail.public_id}</span>
                                    {detail.product_title && (
                                        <>
                                            <span className={classes.dot}>·</span>
                                            <span className={classes.product}>
                                                <IconTicket size={12}/> {detail.product_title}
                                            </span>
                                        </>
                                    )}
                                </div>
                                {eventType === EventType.RECURRING && detail.event_occurrence && timezone && (
                                    <div className={classes.occurrenceRow}>
                                        <IconCalendarEvent size={12}/>
                                        <span>
                                            {formatDateWithLocale(detail.event_occurrence.start_date, 'shortDate', timezone)}
                                            {' · '}
                                            {formatDateWithLocale(detail.event_occurrence.start_date, 'timeOnly', timezone)}
                                            {detail.event_occurrence.label ? ` · ${detail.event_occurrence.label}` : ''}
                                        </span>
                                    </div>
                                )}
                                <div className={classes.statusRow}>{statusBadge}</div>
                            </div>
                        </div>
                        <button
                            type="button"
                            className={classes.closeBtn}
                            onClick={onClose}
                            aria-label={t`Close`}
                        >
                            <IconX size={18}/>
                        </button>
                    </header>

                    <div className={classes.contactRow}>
                        <div className={classes.contactItem}>
                            <IconMail size={14}/>
                            <span>{detail.email}</span>
                        </div>
                    </div>

                    {isCheckedIn && currentCheckIn && (
                        <div className={classes.section}>
                            <div className={classes.sectionLabel}>{t`Check-in history`}</div>
                            <div className={classes.checkInsList}>
                                {detail.check_ins.map(c => (
                                    <div className={classes.checkInRow} key={c.id}>
                                        <div className={classes.checkInBadge}><IconCheck size={12} stroke={3}/></div>
                                        <div>{formatDateTime(c.checked_in_at)}</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {showNotes && detail.notes && (
                        <div className={classes.section}>
                            <div className={classes.sectionLabel}>
                                <IconClipboardText size={14}/> {t`Notes`}
                            </div>
                            <div className={classes.notesBox}>{detail.notes}</div>
                        </div>
                    )}

                    {showQuestions && detail.question_answers && detail.question_answers.length > 0 && (
                        <div className={classes.section}>
                            <div className={classes.sectionLabel}>{t`Answers`}</div>
                            <div className={classes.answersList}>
                                {detail.question_answers.map((qa, idx) => (
                                    <div className={classes.answerRow} key={`${qa.question_id}-${idx}`}>
                                        <div className={classes.answerQ}>{qa.title}</div>
                                        <div className={classes.answerA}>{answerToString(qa.answer)}</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {showOrder && detail.order && (
                        <div className={classes.section}>
                            <div className={classes.sectionLabel}>
                                <IconReceipt2 size={14}/> {t`Order`}
                            </div>
                            <div className={classes.orderGrid}>
                                <div className={classes.orderCell}>
                                    <span className={classes.orderLabel}>{t`Order #`}</span>
                                    <span className={classes.orderValue}>{detail.order.public_id}</span>
                                </div>
                                <div className={classes.orderCell}>
                                    <span className={classes.orderLabel}>{t`Placed`}</span>
                                    <span className={classes.orderValue}>{formatDateTime(detail.order.created_at)}</span>
                                </div>
                                {detail.order.first_name && (
                                    <div className={classes.orderCell}>
                                        <span className={classes.orderLabel}>{t`Purchaser`}</span>
                                        <span className={classes.orderValue}>
                                            {detail.order.first_name} {detail.order.last_name}
                                        </span>
                                    </div>
                                )}
                                {detail.order.email && (
                                    <div className={classes.orderCell}>
                                        <span className={classes.orderLabel}>{t`Purchaser email`}</span>
                                        <span className={classes.orderValue}>{detail.order.email}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {!isLoggedIn && visibility && (!visibility.notes || !visibility.question_answers || !visibility.order_details) && (
                        <div className={classes.hiddenHint}>
                            <Trans>Some details are hidden from public access. Log in to view everything.</Trans>
                        </div>
                    )}

                    <div className={classes.footer}>
                        <Button
                            fullWidth
                            size="lg"
                            color={isCheckedIn ? "red" : "teal"}
                            disabled={!canToggle}
                            loading={isActionPending}
                            onClick={() => detail && onCheckInToggle(detail)}
                            leftSection={isCheckedIn ? <IconX size={18}/> : <IconCheck size={18}/>}
                        >
                            {isCheckedIn ? t`Check out` : t`Check in`}
                        </Button>
                    </div>
                </>
            )}

        </Drawer>
    );
};
