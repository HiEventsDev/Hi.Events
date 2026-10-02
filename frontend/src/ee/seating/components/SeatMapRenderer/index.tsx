import {memo, MouseEvent, PointerEvent, useMemo, useRef, useState} from "react";
import classNames from "classnames";
import {t} from "@lingui/macro";
import {rowLabel} from "../lib/generateSeats.ts";
import {areaBounds, Bounds, boundsBetween, Point} from "../lib/geometry.ts";
import {SeatStates, SeatVisualState} from "../lib/useSeatStates.ts";
import {
    isSeatedElement,
    LabelElement,
    ObjectElement,
    GeneratedSeat,
    ObjectKind,
    SeatedElement,
    SeatMapArea,
    SeatMapBand,
    ZoneElement,
} from "../lib/types.ts";
import {usePanZoom} from "./usePanZoom.ts";
import classes from "./SeatMapRenderer.module.scss";

interface SeatMapRendererProps {
    area: SeatMapArea;
    bands: Map<string, SeatMapBand>;
    seatStates?: SeatStates;
    zoneRemaining?: Record<string, number>;
    zoneSelected?: Record<string, number>;
    interactive?: boolean;
    onSeatClick?: (uid: string) => void;
    onZoneClick?: (uid: string) => void;
    onMarquee?: (bounds: Bounds) => void;
    wheelZoom?: 'always' | 'modifier';
    controls?: (actions: {zoomIn: () => void; zoomOut: () => void; reset: () => void}) => React.ReactNode;
}

const MIN_READABLE_SEAT_PX = 14;
const MIN_TOUCH_TARGET_PX = 26;
const ROW_LABEL_OFFSET = 26;
const COMPANION_RING_GAP = 2.5;
const FALLBACK_BAND_COLOR = '#8f8f96';

const OBJECT_STYLES: Record<ObjectKind, {fill: string; stroke: string; text: string; radius: number; dash?: string}> = {
    stage: {fill: '#33205a', stroke: '#33205a', text: '#ffffff', radius: 6},
    floor: {fill: '#f3eeff', stroke: '#b9a9d8', text: '#40296c', radius: 6, dash: '8 6'},
    bar: {fill: '#552261', stroke: '#552261', text: '#ffffff', radius: 6},
    entrance: {fill: '#e6f9f3', stroke: '#00b894', text: '#00805f', radius: 4, dash: '7 5'},
    pillar: {fill: '#c8c7cc', stroke: '#a8a7ad', text: '#444444', radius: 3},
    wall: {fill: '#8f8f96', stroke: '#70707a', text: '#ffffff', radius: 2},
};

const SEAT_STATE_CLASS: Record<SeatVisualState, string> = {
    free: classes.seatFree,
    selected: classes.seatSelected,
    unavailable: classes.seatUnavailable,
    sold: classes.seatSold,
    held: classes.seatHeld,
    blocked: classes.seatBlocked,
};

const ObjectView = memo(({element}: {element: ObjectElement}) => {
    const style = OBJECT_STYLES[element.kind];
    return (
        <g transform={`translate(${element.x} ${element.y}) rotate(${element.rotation})`}>
            <rect x={-element.w / 2} y={-element.h / 2} width={element.w} height={element.h} rx={style.radius}
                  fill={style.fill} stroke={style.stroke} strokeDasharray={style.dash}/>
            {element.label && <text className={classes.objectLabel} fill={style.text}>{element.label}</text>}
        </g>
    );
});

const LabelView = memo(({element}: {element: LabelElement}) => (
    <text className={classes.textLabel} fontSize={element.size}
          transform={`translate(${element.x} ${element.y}) rotate(${element.rotation})`}>
        {element.text}
    </text>
));

interface SeatViewProps {
    uid: string;
    x: number;
    y: number;
    angle: number;
    number: string;
    size: number;
    color: string;
    state: SeatVisualState;
    accessible: boolean;
    companion: boolean;
    showNumber: boolean;
}

const SeatView = memo(({uid, x, y, angle, number, size, color, state, accessible, companion, showNumber}: SeatViewProps) => {
    const half = size / 2;
    return (
        <g data-uid={uid} data-state={state} transform={`translate(${x} ${y}) rotate(${angle})`}>
            {companion && <circle className={classes.companionMark} r={half + COMPANION_RING_GAP}/>}
            <rect className={classNames(classes.seat, SEAT_STATE_CLASS[state])}
                  x={-half} y={-half} width={size} height={size} rx={size * 0.28} fill={color}/>
            {accessible && <circle className={classes.accessibleMark} r={half * 0.45}/>}
            {!accessible && showNumber && (
                <text className={classes.seatNumber} fontSize={size * 0.48} transform={`rotate(${-angle})`}>{number}</text>
            )}
        </g>
    );
});

interface SeatRowViewProps {
    seats: GeneratedSeat[];
    size: number;
    bands: Map<string, SeatMapBand>;
    stateByUid: Map<string, SeatVisualState> | undefined;
    signature: string;
    showNumbers: boolean;
}

const SeatRowView = memo(({seats, size, bands, stateByUid, showNumbers}: SeatRowViewProps) => seats.map(seat => (
    <SeatView key={seat.uid} uid={seat.uid} x={seat.x} y={seat.y} angle={seat.a} number={seat.n} size={size}
              color={bands.get(seat.band)?.color ?? FALLBACK_BAND_COLOR}
              state={stateByUid?.get(seat.uid) ?? 'free'} accessible={seat.acc} companion={seat.comp}
              showNumber={showNumbers}/>
)), (previous, next) => previous.seats === next.seats
    && previous.size === next.size
    && previous.bands === next.bands
    && previous.signature === next.signature
    && previous.showNumbers === next.showNumbers);

const groupByRow = (seats: GeneratedSeat[]): GeneratedSeat[][] => seats.reduce<GeneratedSeat[][]>((rows, seat, index) => {
    if (index === 0 || seats[index - 1].row !== seat.row) {
        rows.push([]);
    }
    rows[rows.length - 1].push(seat);
    return rows;
}, []);

interface SeatedElementViewProps {
    element: SeatedElement;
    bands: Map<string, SeatMapBand>;
    stateByUid: Map<string, SeatVisualState> | undefined;
    signature: string;
    showNumbers: boolean;
}

const SeatedElementView = memo(({element, bands, stateByUid, showNumbers}: SeatedElementViewProps) => {
    const rows = useMemo(() => groupByRow(element.seats), [element.seats]);
    const firstSeatOfRow = element.type === 'table' || !showNumbers ? [] : rows.map(row => row[0]);

    return (
        <g>
            {element.type === 'table' && (
                <g transform={`translate(${element.x} ${element.y}) rotate(${element.rotation})`}>
                    {element.shape === 'round'
                        ? <circle className={classes.tableSurface} r={element.d / 2}/>
                        : <rect className={classes.tableSurface} x={-element.w / 2} y={-element.h / 2}
                                width={element.w} height={element.h} rx={8}/>}
                    <text className={classes.tableLabel}>{element.label}</text>
                </g>
            )}

            {firstSeatOfRow.map(seat => {
                const radians = (element.rotation * Math.PI) / 180;
                return (
                    <text key={`row-${seat.uid}`} className={classes.rowLabel}
                          x={seat.x - Math.cos(radians) * ROW_LABEL_OFFSET}
                          y={seat.y - Math.sin(radians) * ROW_LABEL_OFFSET}>
                        {rowLabel(element as Exclude<SeatedElement, {type: 'table'}>, seat.row)}
                    </text>
                );
            })}

            {rows.map(row => (
                <SeatRowView key={row[0].uid} seats={row} size={element.seatSize} bands={bands} stateByUid={stateByUid}
                             signature={row.map(seat => stateByUid?.get(seat.uid) ?? 'free').join()}
                             showNumbers={showNumbers}/>
            ))}
        </g>
    );
}, (previous, next) => previous.element === next.element
    && previous.bands === next.bands
    && previous.signature === next.signature
    && previous.showNumbers === next.showNumbers);

interface ZoneViewProps {
    element: ZoneElement;
    color: string;
    remaining: number | undefined;
    selected: number;
    interactive: boolean;
}

const ZoneView = memo(({element, color, remaining, selected, interactive}: ZoneViewProps) => {
    const centerX = element.pts.reduce((sum, [x]) => sum + x, 0) / element.pts.length;
    const centerY = element.pts.reduce((sum, [, y]) => sum + y, 0) / element.pts.length;
    const soldOut = remaining !== undefined && remaining - selected <= 0 && selected === 0;

    return (
        <g data-zone-uid={element.id}>
            <polygon className={classNames(classes.zone, soldOut ? classes.zoneUnavailable : interactive && classes.zoneFree)}
                     points={element.pts.map(point => point.join(',')).join(' ')} fill={color} stroke={color}/>
            <text className={classes.zoneLabel} x={centerX} y={centerY - 8}>{element.label}</text>
            <text className={classes.zoneMeta} x={centerX} y={centerY + 12}>
                {selected > 0 ? t`${selected} selected` : soldOut ? t`Sold out` : t`Standing`}
            </text>
        </g>
    );
});

interface SeatMapElementsProps {
    area: SeatMapArea;
    bands: Map<string, SeatMapBand>;
    seatStates?: SeatStates;
    zoneRemaining?: Record<string, number>;
    zoneSelected?: Record<string, number>;
    interactive?: boolean;
    hideNumbers?: boolean;
    pixelsPerUnit?: number;
}

export const SeatMapElements = memo(({
    area,
    bands,
    seatStates,
    zoneRemaining,
    zoneSelected,
    interactive = false,
    hideNumbers = false,
    pixelsPerUnit,
}: SeatMapElementsProps) => (
    <g className={classNames({[classes.hideNumbers]: hideNumbers})}>
        {area.elements.map(element => {
            if (element.type === 'object') {
                return <ObjectView key={element.id} element={element}/>;
            }
            if (element.type === 'label') {
                return <LabelView key={element.id} element={element}/>;
            }
            if (element.type === 'zone') {
                return <ZoneView key={element.id} element={element}
                                 color={bands.get(element.band)?.color ?? FALLBACK_BAND_COLOR}
                                 remaining={zoneRemaining?.[element.id]}
                                 selected={zoneSelected?.[element.id] ?? 0}
                                 interactive={interactive}/>;
            }
            return isSeatedElement(element) && (
                <SeatedElementView key={element.id} element={element} bands={bands}
                                   stateByUid={seatStates?.stateByUid}
                                   signature={seatStates?.signatureByElement.get(element.id) ?? ''}
                                   showNumbers={pixelsPerUnit === undefined || element.seatSize * pixelsPerUnit >= MIN_READABLE_SEAT_PX}/>
            );
        })}
    </g>
));

export const SeatMapRenderer = ({
    area,
    bands,
    seatStates,
    zoneRemaining,
    zoneSelected,
    interactive = false,
    onSeatClick,
    onZoneClick,
    onMarquee,
    wheelZoom,
    controls,
}: SeatMapRendererProps) => {
    const svgRef = useRef<SVGSVGElement>(null);
    const contentRef = useRef<SVGGElement>(null);
    const bounds = useMemo(() => areaBounds(area), [area]);
    const panZoom = usePanZoom(svgRef, contentRef, bounds, wheelZoom);
    const [marquee, setMarquee] = useState<{start: Point; current: Point} | null>(null);

    const toLayoutPoint = (event: PointerEvent<SVGSVGElement>): Point => {
        const matrix = contentRef.current?.getScreenCTM()?.inverse();
        const point = new DOMPoint(event.clientX, event.clientY).matrixTransform(matrix);
        return {x: point.x, y: point.y};
    };

    const marqueeHandlers = {
        onPointerDown: (event: PointerEvent<SVGSVGElement>) => {
            if (onMarquee && event.shiftKey) {
                event.currentTarget.setPointerCapture(event.pointerId);
                setMarquee({start: toLayoutPoint(event), current: toLayoutPoint(event)});
                return;
            }
            panZoom.handlers.onPointerDown(event);
        },
        onPointerMove: (event: PointerEvent<SVGSVGElement>) => {
            if (marquee) {
                setMarquee({...marquee, current: toLayoutPoint(event)});
                return;
            }
            panZoom.handlers.onPointerMove(event);
        },
        onPointerUp: (event: PointerEvent<SVGSVGElement>) => {
            if (marquee) {
                onMarquee?.(boundsBetween(marquee.start, marquee.current));
                setMarquee(null);
                return;
            }
            panZoom.handlers.onPointerUp(event);
        },
    };

    const handleClick = (event: MouseEvent<SVGSVGElement>) => {
        if (!interactive || panZoom.wasDragged() || event.shiftKey) {
            return;
        }
        const target = event.target as Element;
        const seatElement = target.closest('[data-uid]');
        const seatUid = seatElement?.getAttribute('data-uid');
        if (seatElement && seatUid) {
            const seatSize = seatElement.getBoundingClientRect().width;
            const isTouch = (event.nativeEvent as globalThis.PointerEvent).pointerType === 'touch';
            if (isTouch && seatSize > 0 && seatSize < MIN_TOUCH_TARGET_PX) {
                panZoom.zoomAround(MIN_TOUCH_TARGET_PX * 1.25 / seatSize, event.clientX, event.clientY);
                return;
            }
            onSeatClick?.(seatUid);
            return;
        }
        const zoneUid = target.closest('[data-zone-uid]')?.getAttribute('data-zone-uid');
        if (zoneUid) {
            onZoneClick?.(zoneUid);
        }
    };

    return (
        <>
            <svg ref={svgRef} role="img" aria-label={t`Seat map for ${area.name}`}
                 className={classNames(classes.root, {[classes.interactive]: interactive})}
                 viewBox={`${bounds.x} ${bounds.y} ${bounds.width} ${bounds.height}`}
                 preserveAspectRatio="xMidYMid meet"
                 onClick={handleClick}
                 {...(interactive ? {...panZoom.handlers, ...marqueeHandlers} : {})}>
                <g ref={contentRef}>
                    <SeatMapElements area={area} bands={bands} seatStates={seatStates} zoneRemaining={zoneRemaining}
                                     zoneSelected={zoneSelected} interactive={interactive}
                                     hideNumbers={panZoom.isZooming} pixelsPerUnit={panZoom.pixelsPerUnit}/>
                    {marquee && <rect className={classes.marquee} {...boundsBetween(marquee.start, marquee.current)}/>}
                </g>
            </svg>
            {controls?.(panZoom)}
        </>
    );
};
