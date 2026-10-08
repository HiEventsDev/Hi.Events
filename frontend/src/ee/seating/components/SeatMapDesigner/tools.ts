import {boundsBetween, boundsCenter, boundsContain, elementAt, elementBounds, Point, seatsWithin} from "../lib/geometry.ts";
import {SeatMapArea, SeatMapElement, SeatMapLayout} from "../lib/types.ts";
import {DragRect, newLabel, newObject, newRows, newTable, newZone} from "./newElements.ts";
import {addElement, moveElements, patchElements, regenerate} from "./ops/elements.ts";

export type ToolName = 'select' | 'seats' | 'pan' | 'rows' | 'table' | 'zone' | 'object' | 'label';

export interface Selection {
    elements: string[];
    seats: string[];
}

export const EMPTY_SELECTION: Selection = {elements: [], seats: []};

export const GRID = 10;
const MOVE_STEP = 5;
export const ROTATE_HANDLE_OFFSET = 30;
const ROTATION_STEP = 5;
const HANDLE_HIT_RADIUS = 10;
const POLYGON_CLOSE_RADIUS = 16;
const MIN_OBJECT_SIZE = 16;
const MIN_MARQUEE_SIZE = 4;

export type Gesture =
    | {kind: 'move'; start: Point; before: SeatMapLayout; ids: string[]}
    | {kind: 'rotate'; before: SeatMapLayout; id: string; center: Point}
    | {kind: 'resize'; before: SeatMapLayout; id: string; center: Point}
    | {kind: 'marquee'; target: keyof Selection; start: Point; current: Point; additive: boolean}
    | {kind: 'draw'; from: Point; draft: SeatMapElement | null}
    | {kind: 'polygon'; pts: [number, number][]; cursor: Point}
    | {kind: 'pan'; client: Point};

export interface PointerInfo {
    point: Point;
    client: Point;
    shiftKey: boolean;
    seatUid: string | null;
}

export interface ToolContext {
    layout: SeatMapLayout;
    area: SeatMapArea;
    selection: Selection;
    band: string;
    scale: number;
    preview: (layout: SeatMapLayout) => void;
    commit: (before: SeatMapLayout) => void;
    apply: (change: (layout: SeatMapLayout) => SeatMapLayout) => void;
    select: (selection: Selection) => void;
    panBy: (dx: number, dy: number) => void;
}

export interface Tool {
    down: (context: ToolContext, pointer: PointerInfo, gesture: Gesture | null) => Gesture | null;
    move?: (context: ToolContext, pointer: PointerInfo, gesture: Gesture) => Gesture;
    up?: (context: ToolContext, gesture: Gesture) => Gesture | null;
}

export const snap = (value: number) => Math.round(value / GRID) * GRID;
const step = (value: number) => Math.round(value / MOVE_STEP) * MOVE_STEP;
const snapPoint = (point: Point): Point => ({x: snap(point.x), y: snap(point.y)});

export const selectionHandles = (element: SeatMapElement, scale: number): {rotate: Point | null; resize: Point | null} => {
    const bounds = elementBounds(element);
    return {
        rotate: element.type === 'zone' ? null : {x: bounds.x + bounds.width / 2, y: bounds.y - ROTATE_HANDLE_OFFSET / scale},
        resize: element.type === 'object' ? {x: bounds.x + bounds.width, y: bounds.y + bounds.height} : null,
    };
};

const isNear = (a: Point | null, b: Point, radius: number) => a !== null && Math.hypot(a.x - b.x, a.y - b.y) <= radius;

const place = (context: ToolContext, element: SeatMapElement) => {
    context.apply(layout => addElement(layout, context.area.id, element));
    context.select({elements: [element.id], seats: []});
};

const grabbedHandle = ({area, selection, scale}: ToolContext, point: Point): {element: SeatMapElement; kind: 'rotate' | 'resize'} | null => {
    const element = selection.elements.length === 1 ? area.elements.find(candidate => candidate.id === selection.elements[0]) : undefined;
    if (!element) {
        return null;
    }
    const handles = selectionHandles(element, scale);
    if (isNear(handles.rotate, point, HANDLE_HIT_RADIUS / scale)) {
        return {element, kind: 'rotate'};
    }
    return isNear(handles.resize, point, HANDLE_HIT_RADIUS / scale) ? {element, kind: 'resize'} : null;
};

const selectTool: Tool = {
    down: (context, {point, shiftKey}) => {
        const {area, selection, layout} = context;
        const handle = grabbedHandle(context, point);
        if (handle) {
            return {kind: handle.kind, before: layout, id: handle.element.id, center: boundsCenter(elementBounds(handle.element))};
        }

        const hit = elementAt(area, point);
        if (!hit) {
            return {kind: 'marquee', target: 'elements', start: point, current: point, additive: shiftKey};
        }

        const isSelected = selection.elements.includes(hit.id);
        const ids = shiftKey
            ? (isSelected ? selection.elements.filter(id => id !== hit.id) : [...selection.elements, hit.id])
            : (isSelected ? selection.elements : [hit.id]);
        context.select({elements: ids, seats: []});
        return {kind: 'move', start: point, before: layout, ids};
    },
    move: (context, {point, shiftKey}, gesture) => {
        if (gesture.kind === 'move') {
            context.preview(moveElements(gesture.before, context.area.id, gesture.ids, step(point.x - gesture.start.x), step(point.y - gesture.start.y)));
        }
        if (gesture.kind === 'rotate') {
            const angle = (Math.atan2(point.y - gesture.center.y, point.x - gesture.center.x) * 180) / Math.PI + 90;
            const rotation = shiftKey ? Math.round(angle) : Math.round(angle / ROTATION_STEP) * ROTATION_STEP;
            context.preview(patchElements(gesture.before, context.area.id, [gesture.id], {rotation}));
        }
        if (gesture.kind === 'resize') {
            context.preview(patchElements(gesture.before, context.area.id, [gesture.id], {
                w: Math.max(MIN_OBJECT_SIZE, snap(Math.abs(point.x - gesture.center.x) * 2)),
                h: Math.max(MIN_OBJECT_SIZE, snap(Math.abs(point.y - gesture.center.y) * 2)),
            }));
        }
        return gesture.kind === 'marquee' ? {...gesture, current: point} : gesture;
    },
    up: (context, gesture) => {
        if (gesture.kind === 'marquee') {
            const bounds = boundsBetween(gesture.start, gesture.current);
            const inside = context.area.elements
                .filter(element => boundsContain(bounds, boundsCenter(elementBounds(element))))
                .map(element => element.id);
            const kept = gesture.additive ? context.selection.elements : [];
            context.select({elements: [...new Set([...kept, ...inside])], seats: []});
        } else if ('before' in gesture) {
            context.commit(gesture.before);
        }
        return null;
    },
};

const seatsTool: Tool = {
    down: (context, {point, seatUid, shiftKey}) => {
        if (!seatUid) {
            return {kind: 'marquee', target: 'seats', start: point, current: point, additive: shiftKey};
        }
        const seats = context.selection.seats;
        context.select({
            elements: [],
            seats: seats.includes(seatUid) ? seats.filter(uid => uid !== seatUid) : [...seats, seatUid],
        });
        return null;
    },
    move: (_context, {point}, gesture) => (gesture.kind === 'marquee' ? {...gesture, current: point} : gesture),
    up: (context, gesture) => {
        if (gesture.kind === 'marquee') {
            const bounds = boundsBetween(gesture.start, gesture.current);
            const inside = bounds.width < MIN_MARQUEE_SIZE && bounds.height < MIN_MARQUEE_SIZE ? [] : seatsWithin(context.area, bounds);
            const kept = gesture.additive ? context.selection.seats : [];
            context.select({elements: [], seats: [...new Set([...kept, ...inside])]});
        }
        return null;
    },
};

const panTool: Tool = {
    down: (_context, {client}) => ({kind: 'pan', client}),
    move: (context, {client}, gesture) => {
        if (gesture.kind !== 'pan') {
            return gesture;
        }
        context.panBy(client.x - gesture.client.x, client.y - gesture.client.y);
        return {kind: 'pan', client};
    },
    up: () => null,
};

type DraftBuilder = (area: SeatMapArea, band: string, rect: DragRect) => SeatMapElement | null;

const drawTool = (build: DraftBuilder): Tool => ({
    down: (_context, {point}) => ({kind: 'draw', from: snapPoint(point), draft: null}),
    move: (context, {point}, gesture) => {
        if (gesture.kind !== 'draw') {
            return gesture;
        }
        const draft = build(context.area, context.band, {from: gesture.from, to: point});
        return {...gesture, draft: draft && regenerate(draft)};
    },
    up: (context, gesture) => {
        if (gesture.kind === 'draw' && gesture.draft) {
            place(context, gesture.draft);
        }
        return null;
    },
});

const tableTool: Tool = {
    down: (context, {point}) => {
        place(context, newTable(context.area, context.band, snapPoint(point)));
        return null;
    },
};

const labelTool: Tool = {
    down: (context, {point}) => {
        place(context, newLabel(snapPoint(point)));
        return null;
    },
};

export const closePolygon = (context: ToolContext, pts: [number, number][]) => {
    if (pts.length >= 3) {
        place(context, newZone(context.band, pts));
    }
};

const zoneTool: Tool = {
    down: (context, {point}, gesture) => {
        const pts = gesture?.kind === 'polygon' ? gesture.pts : [];
        if (pts.length > 2 && isNear({x: pts[0][0], y: pts[0][1]}, point, POLYGON_CLOSE_RADIUS / context.scale)) {
            closePolygon(context, pts);
            return null;
        }
        return {kind: 'polygon', pts: [...pts, [snap(point.x), snap(point.y)]], cursor: point};
    },
    move: (_context, {point}, gesture) => (gesture.kind === 'polygon' ? {...gesture, cursor: point} : gesture),
    up: (_context, gesture) => gesture,
};

export const TOOLS: Record<ToolName, Tool> = {
    select: selectTool,
    seats: seatsTool,
    pan: panTool,
    rows: drawTool(newRows),
    object: drawTool((_area, _band, {from, to}) => newObject('stage', {from, to: snapPoint(to)})),
    table: tableTool,
    label: labelTool,
    zone: zoneTool,
};

export const DRAWING_TOOLS: ToolName[] = ['rows', 'table', 'zone', 'object', 'label'];

const isBackdrop = (element: SeatMapElement) => element.type === 'object' || element.type === 'zone';

export const grabsItemAt = (tool: ToolName, context: ToolContext, point: Point): boolean => {
    if (!DRAWING_TOOLS.includes(tool)) {
        return false;
    }
    const hit = elementAt(context.area, point);
    return grabbedHandle(context, point) !== null || (hit !== undefined && !isBackdrop(hit));
};

export const toolForPointerDown = (tool: ToolName, context: ToolContext, point: Point, gesture: Gesture | null): Tool =>
    gesture === null && grabsItemAt(tool, context, point) ? selectTool : TOOLS[tool];

export const placementGhost = (tool: ToolName, context: ToolContext, point: Point): SeatMapElement | null => {
    if (tool === 'table') {
        return regenerate(newTable(context.area, context.band, snapPoint(point)));
    }
    return tool === 'label' ? newLabel(snapPoint(point)) : null;
};
