import {CSSProperties, useMemo} from "react";
import {Alert, Button, Input, SegmentedControl, Switch} from "@mantine/core";
import {IconArrowLeft, IconInfoCircle, IconPrinter} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import classNames from "classnames";
import {Link, useParams, useSearchParams} from "react-router";
import {EventOccurrence, EventType, IdParam} from "../../../../../../types.ts";
import {OccupiedSeat} from "../../../../api/seat-map.client.ts";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {firstUpcomingOccurrence, useGetAllEventOccurrences} from "../../../../../../queries/useGetAllEventOccurrences.ts";
import {useGetEventSeatMap} from "../../../../queries/useGetEventSeatMap.ts";
import {useGetOccupiedSeats} from "../../../../queries/useGetOccupiedSeats.ts";
import {formatDateWithLocale} from "../../../../../../utilites/dates.ts";
import {getEventLocationDisplay} from "../../../../../../utilites/effectiveLocation.ts";
import {Card} from "../../../../../../components/common/Card";
import {LoadingMask} from "../../../../../../components/common/LoadingMask";
import {OccurrenceSelect} from "../../../../../../components/common/OccurrenceSelect";
import {areaBounds} from "../../../lib/geometry.ts";
import {indexLayout, LayoutIndex} from "../../../lib/layoutIndex.ts";
import {isSeatedElement, SeatMapArea} from "../../../lib/types.ts";
import {useSeatStates} from "../../../lib/useSeatStates.ts";
import {SeatMapElements} from "../../../SeatMapRenderer";
import classes from "./SeatingPrint.module.scss";

type PrintMode = 'house' | 'sales';
type PaperName = 'a4' | 'letter';
type Orientation = 'landscape' | 'portrait';

const PAPER: Record<PaperName, {width: number; height: number; rule: string}> = {
    a4: {width: 210, height: 297, rule: 'A4'},
    letter: {width: 216, height: 279, rule: 'Letter'},
};

const PAGE_MARGIN_MM = 10;
const PAGE_CHROME_MM = 54;

const NO_AREAS: SeatMapArea[] = [];
const NO_OCCUPIED_SEATS: OccupiedSeat[] = [];

const seatUidsWithStatus = (seats: OccupiedSeat[], status: OccupiedSeat['status']) =>
    new Set(seats.filter(seat => !seat.is_zone && seat.status === status).map(seat => seat.seat_uid));

interface BandTotals {
    capacity: number;
    sold: number;
}

interface AreaSummary {
    seats: number;
    accessible: number;
    standing: number;
    sold: number;
    held: number;
    blocked: number;
    free: number;
    byBand: Record<string, BandTotals>;
}

const summariseArea = (
    area: SeatMapArea,
    occupiedByUid: Map<string, OccupiedSeat>,
    zoneSold: Record<string, number>,
): AreaSummary => {
    const summary: AreaSummary = {seats: 0, accessible: 0, standing: 0, sold: 0, held: 0, blocked: 0, free: 0, byBand: {}};
    const bandTotals = (key: string) => summary.byBand[key] ??= {capacity: 0, sold: 0};

    for (const element of area.elements) {
        if (element.type === 'zone') {
            summary.standing += element.capacity;
            const totals = bandTotals(element.band);
            totals.capacity += element.capacity;
            totals.sold += zoneSold[element.id] ?? 0;
            continue;
        }

        if (!isSeatedElement(element)) {
            continue;
        }

        for (const seat of element.seats) {
            const totals = bandTotals(seat.band);
            totals.capacity += 1;
            summary.seats += 1;
            if (seat.acc) {
                summary.accessible += 1;
            }

            const status = occupiedByUid.get(seat.uid)?.status;
            if (status === 'SOLD') {
                summary.sold += 1;
                totals.sold += 1;
            } else if (status === 'HELD') {
                summary.held += 1;
            } else if (status === 'BLOCKED') {
                summary.blocked += 1;
            } else {
                summary.free += 1;
            }
        }
    }

    return summary;
};

const seatRows = (seats: OccupiedSeat[], index: LayoutIndex) => seats
    .map(seat => ({
        seat,
        band: index.bands.get(seat.band_key)?.name ?? seat.band_key,
    }))
    .sort((a, b) => a.seat.seat_label.localeCompare(b.seat.seat_label, undefined, {numeric: true}));

export default function SeatingPrint() {
    const {eventId} = useParams();
    const [searchParams, setSearchParams] = useSearchParams();
    const event = useGetEvent(eventId).data;
    const seatMapQuery = useGetEventSeatMap(eventId as IdParam);
    const eventSeatMap = seatMapQuery.data;
    const occurrences = useGetAllEventOccurrences(eventId, !!event).data ?? [];

    const mode: PrintMode = searchParams.get('mode') === 'house' ? 'house' : 'sales';
    const paper: PaperName = searchParams.get('paper') === 'letter' ? 'letter' : 'a4';
    const orientation: Orientation = searchParams.get('orientation') === 'portrait' ? 'portrait' : 'landscape';
    const inkSaver = searchParams.get('ink') === '1';
    const withHeldBack = searchParams.get('held') !== '0';
    const withManifest = searchParams.get('sold') === '1';

    const update = (patch: Record<string, string | null>) => {
        const next = new URLSearchParams(searchParams);
        Object.entries(patch).forEach(([key, value]) => value === null ? next.delete(key) : next.set(key, value));
        setSearchParams(next, {replace: true});
    };

    const defaultOccurrence = firstUpcomingOccurrence(occurrences);
    const occurrenceId = searchParams.get('occurrence') ?? (defaultOccurrence ? String(defaultOccurrence.id) : undefined);
    const occupiedSeatsData = useGetOccupiedSeats(eventId, mode === 'sales' ? occurrenceId : undefined).data;
    const occupiedSeats = mode === 'sales' ? occupiedSeatsData ?? NO_OCCUPIED_SEATS : NO_OCCUPIED_SEATS;
    const index = useMemo(() => eventSeatMap ? indexLayout(eventSeatMap.layout) : null, [eventSeatMap]);
    const sold = useMemo(() => seatUidsWithStatus(occupiedSeats, 'SOLD'), [occupiedSeats]);
    const held = useMemo(() => seatUidsWithStatus(occupiedSeats, 'HELD'), [occupiedSeats]);
    const blocked = useMemo(() => seatUidsWithStatus(occupiedSeats, 'BLOCKED'), [occupiedSeats]);
    const seatStates = useSeatStates(eventSeatMap?.layout.areas ?? NO_AREAS, {sold, held, blocked});

    const salesPath = `/manage/event/${eventId}/seating/sales`;

    if (seatMapQuery.isLoading || !event) {
        return <LoadingMask/>;
    }

    if (!eventSeatMap || !index) {
        return (
            <div className={classes.empty} data-testid="seating-print-empty">
                <Card>
                    <h1>{t`Nothing to print yet`}</h1>
                    <p>{t`This event has no seat map, so there is no plan to print. Attach one in the seating settings.`}</p>
                    <Button component={Link} to={`/manage/event/${eventId}/seating`}>{t`Go to seating settings`}</Button>
                </Card>
            </div>
        );
    }

    const occurrence = occurrences.find((candidate: EventOccurrence) => String(candidate.id) === occurrenceId) ?? null;
    const location = getEventLocationDisplay(event, occurrence);
    const {width, height, rule} = PAPER[paper];
    const paperWidth = orientation === 'landscape' ? height : width;
    const paperHeight = orientation === 'landscape' ? width : height;
    const mapHeight = paperHeight - PAGE_MARGIN_MM * 2 - PAGE_CHROME_MM;

    const occupiedByUid = new Map(occupiedSeats.filter(seat => !seat.is_zone).map(seat => [seat.seat_uid, seat]));
    const zoneSold = occupiedSeats
        .filter(seat => seat.is_zone)
        .reduce<Record<string, number>>((totals, seat) => ({...totals, [seat.seat_uid]: (totals[seat.seat_uid] ?? 0) + 1}), {});

    const zoneRemaining = mode === 'sales'
        ? Object.fromEntries([...index.zones].map(([uid, {zone}]) => [uid, Math.max(0, zone.capacity - (zoneSold[uid] ?? 0))]))
        : undefined;

    const heldBack = withHeldBack && mode === 'sales'
        ? seatRows(occupiedSeats.filter(seat => seat.status === 'BLOCKED'), index)
        : [];
    const manifest = withManifest && mode === 'sales'
        ? seatRows(occupiedSeats.filter(seat => seat.status === 'SOLD'), index)
        : [];

    const pageCount = eventSeatMap.layout.areas.length + (heldBack.length > 0 ? 1 : 0) + (manifest.length > 0 ? 1 : 0);

    const pageStyle = {
        '--paper-width': `${paperWidth}mm`,
        '--map-height': `${mapHeight}mm`,
        '--map-aspect': `${paperWidth} / ${mapHeight}`,
    } as CSSProperties;

    const meta = [
        occurrence ? formatDateWithLocale(occurrence.start_date, 'fullDateTime', event.timezone) : null,
        location?.venueName ?? location?.short ?? null,
    ].filter(Boolean);

    const pageHeader = (area: SeatMapArea) => (
        <header className={classes.pageHeader}>
            <div>
                <h1>{event.title}</h1>
                <p>{meta.join(' · ')}</p>
            </div>
            <div className={classes.pageHeaderRight}>
                <strong>{area.name}</strong>
                <span>{mode === 'sales' ? t`Sales status` : t`House map`}</span>
                <span>{t`Printed ${formatDateWithLocale(new Date().toISOString(), 'shortDateTime', event.timezone)}`}</span>
            </div>
        </header>
    );

    return (
        <div className={classNames(classes.root, {[classes.inkSaver]: inkSaver, [classes.salesMode]: mode === 'sales'})}>
            <style>{`@page { size: ${rule} ${orientation}; margin: ${PAGE_MARGIN_MM}mm; }`}</style>

            <div className={classes.toolbar}>
                <div className={classes.toolbarInner}>
                    <div className={classes.toolbarTop}>
                        <Button variant="subtle" size="compact-sm" component={Link} to={salesPath}
                                leftSection={<IconArrowLeft size={16}/>} data-testid="seating-print-back">
                            {t`Seat sales`}
                        </Button>
                        <div className={classes.summary}>
                            <strong>{event.title}</strong>
                            <span data-testid="seating-print-page-count">{t`Pages: ${pageCount}`}</span>
                        </div>
                        <Button leftSection={<IconPrinter size={16}/>} data-testid="seating-print-button"
                                onClick={() => window.print()}>
                            {t`Print`}
                        </Button>
                    </div>

                    <div className={classes.toolbarOptions}>
                        <Input.Wrapper label={t`Show`}>
                            <div className={classes.optionRow}>
                                <SegmentedControl value={mode} data-testid="seating-print-mode" size="xs"
                                                  onChange={value => update({mode: value})}
                                                  data={[{value: 'sales', label: t`Sales status`}, {value: 'house', label: t`House map`}]}/>
                            </div>
                        </Input.Wrapper>

                        {event.type === EventType.RECURRING && occurrences.length > 0 && (
                            <OccurrenceSelect occurrences={occurrences} timezone={event.timezone} label={t`Date`} size="xs"
                                              value={occurrenceId ?? null}
                                              onChange={value => update({occurrence: value})}/>
                        )}

                        <Input.Wrapper label={t`Paper`}>
                            <div className={classes.optionRow}>
                                <SegmentedControl value={paper} size="xs" onChange={value => update({paper: value})}
                                                  data={[{value: 'a4', label: 'A4'}, {value: 'letter', label: t`Letter`}]}/>
                                <SegmentedControl value={orientation} size="xs" onChange={value => update({orientation: value})}
                                                  data={[{value: 'landscape', label: t`Landscape`}, {value: 'portrait', label: t`Portrait`}]}/>
                            </div>
                        </Input.Wrapper>

                        <Input.Wrapper label={t`Include`}>
                            <div className={classes.optionRow}>
                                <Switch label={t`Ink saver`} size="sm" checked={inkSaver}
                                        onChange={change => update({ink: change.currentTarget.checked ? '1' : null})}/>
                                {mode === 'sales' && (
                                    <>
                                        <Switch label={t`Held-back seats`} description={t`Adds a page`} size="sm"
                                                checked={withHeldBack} data-testid="seating-print-held-back-toggle"
                                                onChange={change => update({held: change.currentTarget.checked ? null : '0'})}/>
                                        <Switch label={t`Sold seats`} description={t`Adds a page, names attendees`} size="sm"
                                                checked={withManifest} data-testid="seating-print-manifest-toggle"
                                                onChange={change => update({sold: change.currentTarget.checked ? '1' : null})}/>
                                    </>
                                )}
                            </div>
                        </Input.Wrapper>
                    </div>

                    {withManifest && mode === 'sales' && (
                        <Alert color="orange" icon={<IconInfoCircle size={18}/>} className={classes.warning}>
                            {t`Attendee names appear on the printout. Handle it accordingly.`}
                        </Alert>
                    )}
                </div>
            </div>

            <div className={classes.pages}>
                {eventSeatMap.layout.areas.map(area => {
                    const summary = summariseArea(area, occupiedByUid, zoneSold);
                    const bounds = areaBounds(area);

                    return (
                        <section key={area.id} className={classes.page} style={pageStyle} data-testid="seating-print-page">
                            {pageHeader(area)}

                            <div className={classes.map}>
                                <svg viewBox={`${bounds.x} ${bounds.y} ${bounds.width} ${bounds.height}`}
                                     preserveAspectRatio="xMidYMid meet" role="img"
                                     aria-label={t`Seat map for ${area.name}`}>
                                    <SeatMapElements area={area} bands={index.bands} seatStates={seatStates} zoneRemaining={zoneRemaining}/>
                                </svg>
                            </div>

                            <footer className={classes.pageFooter}>
                                <div className={classes.legend}>
                                    {eventSeatMap.layout.bands.map(band => {
                                        const totals = summary.byBand[band.key];
                                        return totals && totals.capacity > 0 && (
                                            <span key={band.key}>
                                                <i style={{background: band.color}}/>
                                                {band.name}
                                                <em>{mode === 'sales'
                                                    ? t`${totals.sold} of ${totals.capacity} sold`
                                                    : t`${totals.capacity} places`}</em>
                                            </span>
                                        );
                                    })}
                                </div>

                                <div className={classes.counts} data-testid="seating-print-counts">
                                    <span>{t`${summary.seats} seats`}</span>
                                    {summary.standing > 0 && <span>{t`${summary.standing} standing`}</span>}
                                    {summary.accessible > 0 && <span>{t`${summary.accessible} accessible`}</span>}
                                    {mode === 'sales' && (
                                        <>
                                            <span className={classes.stateSold}>{t`${summary.sold} sold`}</span>
                                            <span className={classes.stateHeld}>{t`${summary.held} in a basket`}</span>
                                            <span className={classes.stateBlocked}>{t`${summary.blocked} held back`}</span>
                                            <span>{t`${summary.free} free`}</span>
                                        </>
                                    )}
                                </div>
                            </footer>
                        </section>
                    );
                })}

                {heldBack.length > 0 && (
                    <section className={classes.page} style={pageStyle} data-testid="seating-print-held-back">
                        <h2 className={classes.listTitle}>{t`Held back from sale`}</h2>
                        <table className={classes.list}>
                            <thead>
                            <tr>
                                <th>{t`Seat`}</th>
                                <th>{t`Band`}</th>
                                <th>{t`Reason`}</th>
                            </tr>
                            </thead>
                            <tbody>
                            {heldBack.map(({seat, band}, position) => (
                                <tr key={`${seat.seat_uid}-${position}`}>
                                    <td>{seat.seat_label}</td>
                                    <td>{band}</td>
                                    <td>{seat.block_reason ?? '—'}</td>
                                </tr>
                            ))}
                            </tbody>
                        </table>
                    </section>
                )}

                {manifest.length > 0 && (
                    <section className={classes.page} style={pageStyle} data-testid="seating-print-manifest">
                        <h2 className={classes.listTitle}>{t`Sold seats`}</h2>
                        <table className={classes.list}>
                            <thead>
                            <tr>
                                <th>{t`Seat`}</th>
                                <th>{t`Band`}</th>
                                <th>{t`Attendee`}</th>
                            </tr>
                            </thead>
                            <tbody>
                            {manifest.map(({seat, band}, position) => (
                                <tr key={`${seat.seat_uid}-${position}`}>
                                    <td>{seat.seat_label}</td>
                                    <td>{band}</td>
                                    <td>{seat.attendee_name ?? '—'}</td>
                                </tr>
                            ))}
                            </tbody>
                        </table>
                    </section>
                )}
            </div>
        </div>
    );
}
