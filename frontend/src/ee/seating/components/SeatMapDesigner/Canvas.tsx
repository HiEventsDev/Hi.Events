import {PointerEvent as ReactPointerEvent, RefObject, useEffect, useImperativeHandle, useMemo, useState} from "react";
import {t} from "@lingui/macro";
import {boundsBetween, elementBounds, Point} from "../lib/geometry.ts";
import {SeatMapArea, SeatMapElement, SeatMapBand} from "../lib/types.ts";
import {useSeatStates} from "../lib/useSeatStates.ts";
import {SeatMapElements} from "../SeatMapRenderer";
import {CanvasView} from "./useCanvasView.ts";
import {
    Gesture,
    grabsItemAt,
    GRID,
    placementGhost,
    PointerInfo,
    ROTATE_HANDLE_OFFSET,
    selectionHandles,
    ToolContext,
    ToolName,
} from "./tools.ts";
import classes from "./SeatMapDesigner.module.scss";

export interface HoverHandle {
    setPoint: (point: Point | null) => void;
}

interface CanvasProps {
    svgRef: RefObject<SVGSVGElement | null>;
    hoverRef: RefObject<HoverHandle | null>;
    area: SeatMapArea;
    bands: Map<string, SeatMapBand>;
    view: CanvasView;
    tool: ToolName;
    context: ToolContext;
    selectedElements: SeatMapElement[];
    selectedSeatUids: Set<string>;
    gesture: Gesture | null;
    isHoverTracked: boolean;
    toPointerInfo: (event: ReactPointerEvent) => PointerInfo;
    onPointerDown: (pointer: PointerInfo, event: ReactPointerEvent) => void;
    onPointerMove: (pointer: PointerInfo) => void;
    onPointerUp: () => void;
    onPointerLeave: () => void;
    onWheel: (event: WheelEvent) => void;
}

const NUMBERS_VISIBLE_FROM_SCALE = 0.75;
const HANDLE_RADIUS = 6;
const GRID_DOTS_EVERY = GRID * 5;

const Ghost = ({element}: {element: SeatMapElement}) => {
    if (element.type === 'object') {
        return <rect className={classes.ghost} transform={`translate(${element.x} ${element.y}) rotate(${element.rotation})`}
                     x={-element.w / 2} y={-element.h / 2} width={element.w} height={element.h} rx={6}/>;
    }
    if (element.type === 'label') {
        return <text className={classes.ghostText} x={element.x} y={element.y} fontSize={element.size}>{element.text}</text>;
    }
    if (element.type === 'zone') {
        return null;
    }
    const half = element.seatSize / 2;
    return (
        <g>
            {element.type === 'table' && element.shape === 'round' && (
                <circle className={classes.ghost} cx={element.x} cy={element.y} r={element.d / 2}/>
            )}
            {element.seats.map(seat => (
                <rect key={seat.uid} className={classes.ghost} x={seat.x - half} y={seat.y - half}
                      width={element.seatSize} height={element.seatSize} rx={element.seatSize * 0.28}/>
            ))}
        </g>
    );
};

interface HoverLayerProps {
    svgRef: RefObject<SVGSVGElement | null>;
    hoverRef: RefObject<HoverHandle | null>;
    tool: ToolName;
    context: ToolContext;
    isTracked: boolean;
}

const HoverLayer = ({svgRef, hoverRef, tool, context, isTracked}: HoverLayerProps) => {
    const [point, setPoint] = useState<Point | null>(null);
    useImperativeHandle(hoverRef, () => ({setPoint}), []);

    const hovered = isTracked ? point : null;
    const isOverItem = hovered !== null && grabsItemAt(tool, context, hovered);
    const ghost = hovered !== null && !isOverItem ? placementGhost(tool, context, hovered) : null;

    useEffect(() => {
        const svg = svgRef.current;
        if (svg) {
            svg.dataset.tool = isOverItem ? 'select' : tool;
            svg.dataset.overItem = String(isOverItem);
        }
    }, [svgRef, isOverItem, tool]);

    return ghost && <Ghost element={ghost}/>;
};

const GestureOverlay = ({gesture}: {gesture: Gesture}) => {
    if (gesture.kind === 'marquee') {
        const bounds = boundsBetween(gesture.start, gesture.current);
        return <rect className={classes.marquee} {...bounds}/>;
    }
    if (gesture.kind === 'draw') {
        if (!gesture.draft) {
            return null;
        }
        const bounds = elementBounds(gesture.draft);
        return (
            <>
                <Ghost element={gesture.draft}/>
                <rect className={classes.draft} {...bounds}/>
                {gesture.draft.type === 'block' && (
                    <text className={classes.draftSize} x={bounds.x} y={bounds.y - 8}>
                        {`${gesture.draft.cols} × ${gesture.draft.rows}`}
                    </text>
                )}
            </>
        );
    }
    if (gesture.kind === 'polygon') {
        const points = [...gesture.pts, [gesture.cursor.x, gesture.cursor.y]].map(point => point.join(',')).join(' ');
        return (
            <>
                <polyline className={classes.draft} points={points}/>
                <circle className={classes.polygonStart} cx={gesture.pts[0][0]} cy={gesture.pts[0][1]} r={HANDLE_RADIUS}/>
            </>
        );
    }
    return null;
};

export const Canvas = ({
    svgRef,
    hoverRef,
    area,
    bands,
    view,
    tool,
    context,
    selectedElements,
    selectedSeatUids,
    gesture,
    isHoverTracked,
    toPointerInfo,
    onPointerDown,
    onPointerMove,
    onPointerUp,
    onPointerLeave,
    onWheel,
}: CanvasProps) => {
    useEffect(() => {
        const svg = svgRef.current;
        if (!svg) {
            return;
        }
        const handleWheel = (event: WheelEvent) => {
            event.preventDefault();
            onWheel(event);
        };
        svg.addEventListener('wheel', handleWheel, {passive: false});
        return () => svg.removeEventListener('wheel', handleWheel);
    }, [svgRef, onWheel]);

    const areas = useMemo(() => [area], [area]);
    const seatStates = useSeatStates(areas, {selected: selectedSeatUids});
    const handles = selectedElements.length === 1 ? selectionHandles(selectedElements[0], view.scale) : null;
    const gridSize = GRID_DOTS_EVERY * view.scale;

    return (
        <svg ref={svgRef} className={classes.canvas}
             data-testid="seat-map-designer-canvas"
             role="application" aria-label={t`Seat map canvas for ${area.name}`}
             style={{backgroundSize: `${gridSize}px ${gridSize}px`, backgroundPosition: `${view.x}px ${view.y}px`}}
             onPointerDown={event => {
                 event.currentTarget.setPointerCapture(event.pointerId);
                 onPointerDown(toPointerInfo(event), event);
             }}
             onPointerMove={event => onPointerMove(toPointerInfo(event))}
             onPointerUp={onPointerUp}
             onPointerCancel={onPointerUp}
             onPointerLeave={onPointerLeave}>
            <g transform={`translate(${view.x} ${view.y}) scale(${view.scale})`}>
                <SeatMapElements area={area} bands={bands} hideNumbers={view.scale < NUMBERS_VISIBLE_FROM_SCALE}
                                 seatStates={seatStates}/>

                {selectedElements.map(element => (
                    <rect key={element.id} className={classes.selectionOutline} {...elementBounds(element)}/>
                ))}

                {handles?.rotate && (
                    <>
                        <line className={classes.handleStem} x1={handles.rotate.x} y1={handles.rotate.y}
                              x2={handles.rotate.x} y2={handles.rotate.y + ROTATE_HANDLE_OFFSET / view.scale}/>
                        <circle className={classes.handle} cx={handles.rotate.x} cy={handles.rotate.y}
                                r={HANDLE_RADIUS / view.scale}/>
                    </>
                )}
                {handles?.resize && (
                    <circle className={classes.handle} cx={handles.resize.x} cy={handles.resize.y}
                            r={HANDLE_RADIUS / view.scale}/>
                )}

                {gesture && <GestureOverlay gesture={gesture}/>}
                <HoverLayer svgRef={svgRef} hoverRef={hoverRef} tool={tool} context={context} isTracked={isHoverTracked}/>
            </g>
        </svg>
    );
};
