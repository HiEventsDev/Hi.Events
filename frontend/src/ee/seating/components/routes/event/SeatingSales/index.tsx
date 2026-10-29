import {useMemo, useState} from "react";
import {Button, MultiSelect, SegmentedControl, TextInput} from "@mantine/core";
import {IconLock, IconLockOpen, IconPrinter} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {Link, useParams} from "react-router";
import {EventOccurrenceStatus, EventType, IdParam} from "../../../../../../types.ts";
import {OccupiedSeat, SeatBlockDates} from "../../../../api/seat-map.client.ts";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {firstUpcomingOccurrence, useGetAllEventOccurrences} from "../../../../../../queries/useGetAllEventOccurrences.ts";
import {useGetEventSeatMap} from "../../../../queries/useGetEventSeatMap.ts";
import {useGetOccupiedSeats} from "../../../../queries/useGetOccupiedSeats.ts";
import {useUpdateSeatBlocks} from "../../../../mutations/useUpdateSeatBlocks.ts";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {PageBody} from "../../../../../../components/common/PageBody";
import {PageTitle} from "../../../../../../components/common/PageTitle";
import {OccurrenceSelect} from "../../../../../../components/common/OccurrenceSelect";
import {formatOccurrenceLabel} from "../../../../../../components/common/OccurrenceSelect/occurrenceSelectUtils.ts";
import {seatsWithin} from "../../../lib/geometry.ts";
import {indexLayout, seatLabel} from "../../../lib/layoutIndex.ts";
import {SeatMapArea} from "../../../lib/types.ts";
import {useSeatStates} from "../../../lib/useSeatStates.ts";
import {useLicensedFeature} from "../../../../../licensing/hooks/useLicensedFeature.ts";
import {FeatureFlag} from "../../../../../../constants/featureFlags.ts";
import {AreaTabs} from "../../../AreaTabs.tsx";
import {SeatMapRenderer} from "../../../SeatMapRenderer";
import classes from "./SeatingSales.module.scss";

const NO_AREAS: SeatMapArea[] = [];

type DateScope = 'date' | 'upcoming' | 'choose';

const seatUidsWithStatus = (seats: OccupiedSeat[], status: OccupiedSeat['status']) =>
    new Set(seats.filter(seat => seat.status === status).map(seat => seat.seat_uid));

export default function SeatingSales() {
    const {eventId} = useParams();
    const event = useGetEvent(eventId).data;
    const eventSeatMap = useGetEventSeatMap(eventId as IdParam).data;
    const occurrences = useGetAllEventOccurrences(eventId, !!event).data ?? [];
    const [chosenOccurrenceId, setChosenOccurrenceId] = useState<string | null>(null);
    const defaultOccurrence = firstUpcomingOccurrence(occurrences);
    const occurrenceId = chosenOccurrenceId ?? (defaultOccurrence ? String(defaultOccurrence.id) : undefined);

    const occupiedSeatsData = useGetOccupiedSeats(eventId, occurrenceId).data;
    const blockMutation = useUpdateSeatBlocks(eventId);
    const canHoldBackSeats = !useLicensedFeature(FeatureFlag.SEATING).isSetupLocked;
    const [areaId, setAreaId] = useState<string | null>(null);
    const [selected, setSelected] = useState<Set<string>>(() => new Set());
    const [inspected, setInspected] = useState<OccupiedSeat | null>(null);
    const [reason, setReason] = useState('');
    const [dateScope, setDateScope] = useState<DateScope>('date');
    const [chosenDateIds, setChosenDateIds] = useState<string[]>([]);

    const index = useMemo(() => eventSeatMap ? indexLayout(eventSeatMap.layout) : null, [eventSeatMap]);
    const occupiedSeats = useMemo(() => (occupiedSeatsData ?? []).filter(seat => !seat.is_zone), [occupiedSeatsData]);
    const occupiedByUid = useMemo(() => new Map(occupiedSeats.map(seat => [seat.seat_uid, seat])), [occupiedSeats]);
    const sold = useMemo(() => seatUidsWithStatus(occupiedSeats, 'SOLD'), [occupiedSeats]);
    const held = useMemo(() => seatUidsWithStatus(occupiedSeats, 'HELD'), [occupiedSeats]);
    const blocked = useMemo(() => seatUidsWithStatus(occupiedSeats, 'BLOCKED'), [occupiedSeats]);
    const seatStates = useSeatStates(eventSeatMap?.layout.areas ?? NO_AREAS, {selected, sold, held, blocked});

    if (!event || !eventSeatMap || !index) {
        return null;
    }

    const area = eventSeatMap.layout.areas.find(candidate => candidate.id === areaId) ?? eventSeatMap.layout.areas[0];
    const counts = (occupiedSeatsData ?? []).reduce<Record<string, number>>(
        (totals, seat) => ({...totals, [seat.status]: (totals[seat.status] ?? 0) + 1}),
        {},
    );
    const isRecurring = event.type === EventType.RECURRING;
    const selectableDates = occurrences.filter(occurrence => occurrence.status !== EventOccurrenceStatus.CANCELLED);
    const upcomingDateCount = selectableDates.filter(occurrence => !occurrence.is_past).length;
    const scope: DateScope = isRecurring ? dateScope : 'date';
    const dates: SeatBlockDates = scope === 'upcoming'
        ? {all_upcoming_dates: true}
        : {event_occurrence_ids: scope === 'choose' ? chosenDateIds : [occurrenceId as IdParam]};
    const dateCount = scope === 'upcoming' ? upcomingDateCount : scope === 'choose' ? chosenDateIds.length : 1;
    const hasDates = scope === 'upcoming' || dateCount > 0;
    const selectedBlocked = scope === 'date' ? [...selected].filter(uid => blocked.has(uid)) : [...selected];
    const selectedFree = scope === 'date' ? [...selected].filter(uid => !occupiedByUid.has(uid)) : [...selected];
    const isSelectable = (uid: string) => !sold.has(uid) && !held.has(uid);

    const handleSeatClick = (uid: string) => {
        setInspected(occupiedByUid.get(uid) ?? null);
        if (isSelectable(uid)) {
            setSelected(current => {
                const next = new Set(current);
                if (!next.delete(uid)) {
                    next.add(uid);
                }
                return next;
            });
        }
    };

    const run = (change: Parameters<typeof blockMutation.mutate>[0]) => blockMutation.mutate(change, {
        onSuccess: result => {
            if (result.action === 'release') {
                showSuccess(t`${result.released} seats released for sale`);
            } else {
                const skipped = result.skipped.reduce((total, date) => total + date.seat_uids.length, 0);
                showSuccess(skipped > 0
                    ? t`${result.blocked} seats held back. ${skipped} already sold or in a basket were skipped.`
                    : t`${result.blocked} seats held back`);
            }
            setSelected(new Set());
            setInspected(null);
            setReason('');
        },
        onError: (error: any) => showError(error?.response?.data?.message ?? t`The seats could not be updated`),
    });

    return (
        <PageBody>
            <PageTitle subheading={t`See what is sold for each date and hold seats back from sale. Hold Shift and drag to select many seats.`}>
                {t`Seat sales`}
            </PageTitle>

            <div className={classes.toolbar}>
                {isRecurring && occurrences.length > 0 && (
                    <OccurrenceSelect occurrences={occurrences} timezone={event.timezone} value={occurrenceId ?? null}
                                      onChange={value => {
                                          setChosenOccurrenceId(value);
                                          setSelected(new Set());
                                          setInspected(null);
                                      }}/>
                )}
                <AreaTabs areas={eventSeatMap.layout.areas} value={area.id} onChange={setAreaId}/>
                <Button variant="subtle" component={Link} to={`/manage/event/${eventId}/seating`}>{t`Seating settings`}</Button>
                <Button variant="light" leftSection={<IconPrinter size={16}/>} component={Link} target="_blank"
                        data-testid="seating-sales-print-button"
                        to={`/manage/event/${eventId}/seating/print?mode=sales${occurrenceId ? `&occurrence=${occurrenceId}` : ''}`}>
                    {t`Print map`}
                </Button>
            </div>

            <div className={classes.layout}>
                <div className={classes.map} data-testid="seating-sales-map">
                    <SeatMapRenderer area={area} bands={index.bands} seatStates={seatStates} interactive
                                     onSeatClick={handleSeatClick}
                                     onMarquee={bounds => setSelected(current => new Set([
                                         ...current,
                                         ...seatsWithin(area, bounds).filter(isSelectable),
                                     ]))}/>
                </div>

                <aside className={classes.panel}>
                    <div className={classes.legend}>
                        <span><i className={classes.sold}/>{t`Sold`} · {counts.SOLD ?? 0}</span>
                        <span><i className={classes.held}/>{t`In a basket`} · {counts.HELD ?? 0}</span>
                        <span><i className={classes.blocked}/>{t`Held back`} · {counts.BLOCKED ?? 0}</span>
                    </div>

                    {inspected && (
                        <div className={classes.detail} data-testid="seating-sales-seat-detail">
                            <strong>{inspected.seat_label}</strong>
                            {inspected.status === 'SOLD' && <span>{t`Sold to ${inspected.attendee_name ?? t`an attendee`}`}</span>}
                            {inspected.status === 'HELD' && <span>{t`In someone's basket`}</span>}
                            {inspected.status === 'BLOCKED' && <span>{inspected.block_reason ?? t`Held back from sale`}</span>}
                            {inspected.attendee_public_id && (
                                <Link to={`/manage/event/${eventId}/attendees?query=${inspected.attendee_public_id}`}>{t`View attendee`}</Link>
                            )}
                        </div>
                    )}

                    {selected.size === 0 && !inspected && (
                        <p className={classes.hint}>{t`Click seats to select them, then hold them back or release them.`}</p>
                    )}

                    {isRecurring && selected.size > 0 && (
                        <div className={classes.action}>
                            <SegmentedControl size="xs" fullWidth value={scope} data-testid="seating-sales-date-scope"
                                              onChange={value => {
                                                  setDateScope(value as DateScope);
                                                  if (value === 'choose' && chosenDateIds.length === 0 && occurrenceId) {
                                                      setChosenDateIds([occurrenceId]);
                                                  }
                                              }}
                                              data={[
                                                  {value: 'date', label: t`This date`},
                                                  {value: 'upcoming', label: t`All upcoming`},
                                                  {value: 'choose', label: t`Choose dates`},
                                              ]}/>
                            {scope === 'choose' && (
                                <MultiSelect size="xs" label={t`Dates`} searchable clearable limit={50}
                                             data-testid="seating-sales-dates-select"
                                             placeholder={t`Pick dates`}
                                             data={selectableDates.map(occurrence => ({
                                                 value: String(occurrence.id),
                                                 label: formatOccurrenceLabel(occurrence, event.timezone),
                                             }))}
                                             value={chosenDateIds} onChange={setChosenDateIds}/>
                            )}
                            {scope !== 'date' && (
                                <span className={classes.hint}>{t`Seats sold or in a basket on a date are skipped for that date.`}</span>
                            )}
                        </div>
                    )}

                    {canHoldBackSeats && selectedFree.length > 0 && (
                        <div className={classes.action}>
                            <span className={classes.hint}>{selectedFree.map(uid => seatLabel(index, uid)).slice(0, 6).join(', ')}</span>
                            <TextInput size="xs" label={t`Reason (optional)`} placeholder={t`Sound desk, sponsor seats…`} maxLength={255}
                                       value={reason} onChange={event => setReason(event.currentTarget.value)}/>
                            <Button leftSection={<IconLock size={16}/>} loading={blockMutation.isPending} disabled={!hasDates}
                                    data-testid="seating-sales-block-button"
                                    onClick={() => run({action: 'block', dates, seatUids: selectedFree, reason: reason.trim() || null})}>
                                {scope === 'date' ? t`Hold back ${selectedFree.length} seats` : t`Hold back ${selectedFree.length} seats on ${dateCount} dates`}
                            </Button>
                        </div>
                    )}

                    {selectedBlocked.length > 0 && (
                        <Button variant="light" leftSection={<IconLockOpen size={16}/>} loading={blockMutation.isPending} disabled={!hasDates}
                                data-testid="seating-sales-release-button"
                                onClick={() => run({action: 'release', dates, seatUids: selectedBlocked})}>
                            {scope === 'date' ? t`Release ${selectedBlocked.length} seats` : t`Release ${selectedBlocked.length} seats on ${dateCount} dates`}
                        </Button>
                    )}
                </aside>
            </div>
        </PageBody>
    );
}
