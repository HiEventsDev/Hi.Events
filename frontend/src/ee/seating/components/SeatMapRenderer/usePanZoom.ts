import {PointerEvent as ReactPointerEvent, RefObject, useCallback, useEffect, useRef, useState} from "react";
import {Bounds} from "../lib/geometry.ts";

const MIN_SCALE = 0.4;
const WHEEL_ZOOM_SENSITIVITY = 0.002;
const MAX_SCALE = 6;
const DRAG_THRESHOLD_PX = 5;
const WHEEL_SETTLE_MS = 150;

interface View {
    x: number;
    y: number;
    scale: number;
}

const FIT: View = {x: 0, y: 0, scale: 1};

const clampScale = (scale: number) => Math.min(MAX_SCALE, Math.max(MIN_SCALE, scale));

const transformFor = (view: View, bounds: Bounds): string => {
    const centerX = bounds.x + bounds.width / 2;
    const centerY = bounds.y + bounds.height / 2;
    return `translate(${centerX + view.x} ${centerY + view.y}) scale(${view.scale}) translate(${-centerX} ${-centerY})`;
};

export const usePanZoom = (
    svgRef: RefObject<SVGSVGElement | null>,
    contentRef: RefObject<SVGGElement | null>,
    bounds: Bounds,
    wheelZoom: 'always' | 'modifier' = 'modifier',
) => {
    const view = useRef<View>(FIT);
    const boundsRef = useRef(bounds);
    const frame = useRef<number | null>(null);
    const wheelSettle = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [pixelsPerUnit, setPixelsPerUnit] = useState(0);
    const [isZooming, setIsZooming] = useState(false);
    const pointers = useRef(new Map<number, {x: number; y: number}>());
    const dragged = useRef(false);
    const pinchDistance = useRef<number | null>(null);

    const writeTransform = useCallback(() => {
        frame.current = null;
        contentRef.current?.setAttribute('transform', transformFor(view.current, boundsRef.current));
    }, [contentRef]);

    const unitsPerPixel = useCallback(() => {
        const rect = svgRef.current?.getBoundingClientRect();
        if (!rect || rect.width === 0) {
            return 1;
        }
        return Math.max(boundsRef.current.width / rect.width, boundsRef.current.height / rect.height);
    }, [svgRef]);

    const commitScale = useCallback(() => {
        setPixelsPerUnit(view.current.scale / unitsPerPixel());
        setIsZooming(false);
    }, [unitsPerPixel]);

    const applyView = useCallback((next: View) => {
        view.current = next;
        if (frame.current === null) {
            frame.current = requestAnimationFrame(writeTransform);
        }
    }, [writeTransform]);

    boundsRef.current = bounds;

    useEffect(() => {
        view.current = FIT;
        writeTransform();
        commitScale();
    }, [bounds.x, bounds.y, bounds.width, bounds.height, writeTransform, commitScale]);

    useEffect(() => () => {
        if (frame.current !== null) cancelAnimationFrame(frame.current);
        if (wheelSettle.current !== null) clearTimeout(wheelSettle.current);
    }, []);

    useEffect(() => {
        const svg = svgRef.current;
        if (!svg) {
            return;
        }
        const observer = new ResizeObserver(commitScale);
        observer.observe(svg);
        return () => observer.disconnect();
    }, [svgRef, commitScale]);

    const zoomView = useCallback((factor: number, clientX?: number, clientY?: number) => {
        const current = view.current;
        const nextScale = clampScale(current.scale * factor);
        const rect = svgRef.current?.getBoundingClientRect();
        if (!rect || clientX === undefined || clientY === undefined) {
            applyView({...current, scale: nextScale});
            return;
        }
        const units = unitsPerPixel();
        const px = (clientX - rect.left - rect.width / 2) * units;
        const py = (clientY - rect.top - rect.height / 2) * units;
        const ratio = nextScale / current.scale;
        applyView({scale: nextScale, x: px - (px - current.x) * ratio, y: py - (py - current.y) * ratio});
    }, [svgRef, unitsPerPixel, applyView]);

    const zoomAround = useCallback((factor: number, clientX?: number, clientY?: number) => {
        zoomView(factor, clientX, clientY);
        commitScale();
    }, [zoomView, commitScale]);

    useEffect(() => {
        const svg = svgRef.current;
        if (!svg) {
            return;
        }
        const onWheel = (event: WheelEvent) => {
            if (wheelZoom === 'modifier' && !event.ctrlKey && !event.metaKey) {
                return;
            }
            event.preventDefault();
            setIsZooming(true);
            zoomView(Math.exp(-event.deltaY * WHEEL_ZOOM_SENSITIVITY), event.clientX, event.clientY);
            if (wheelSettle.current !== null) clearTimeout(wheelSettle.current);
            wheelSettle.current = setTimeout(commitScale, WHEEL_SETTLE_MS);
        };
        svg.addEventListener('wheel', onWheel, {passive: false});
        return () => svg.removeEventListener('wheel', onWheel);
    }, [svgRef, zoomView, commitScale, wheelZoom]);

    const onPointerDown = (event: ReactPointerEvent) => {
        pointers.current.set(event.pointerId, {x: event.clientX, y: event.clientY});
        dragged.current = false;
        pinchDistance.current = null;
    };

    const onPointerMove = (event: ReactPointerEvent) => {
        const previous = pointers.current.get(event.pointerId);
        if (!previous) {
            return;
        }
        pointers.current.set(event.pointerId, {x: event.clientX, y: event.clientY});

        if (pointers.current.size === 2) {
            const [a, b] = [...pointers.current.values()];
            const distance = Math.hypot(a.x - b.x, a.y - b.y);
            if (pinchDistance.current !== null && distance > 0) {
                setIsZooming(true);
                zoomView(distance / pinchDistance.current, (a.x + b.x) / 2, (a.y + b.y) / 2);
            }
            pinchDistance.current = distance;
            dragged.current = true;
            return;
        }

        const dx = event.clientX - previous.x;
        const dy = event.clientY - previous.y;
        if (!dragged.current && Math.hypot(dx, dy) < DRAG_THRESHOLD_PX) {
            pointers.current.set(event.pointerId, previous);
            return;
        }
        dragged.current = true;
        const units = unitsPerPixel();
        applyView({...view.current, x: view.current.x + dx * units, y: view.current.y + dy * units});
    };

    const onPointerUp = (event: ReactPointerEvent) => {
        if (!pointers.current.delete(event.pointerId)) {
            return;
        }
        pinchDistance.current = null;
        commitScale();
    };

    return {
        pixelsPerUnit,
        isZooming,
        wasDragged: () => dragged.current,
        zoomAround,
        zoomIn: () => zoomAround(1.4),
        zoomOut: () => zoomAround(1 / 1.4),
        reset: () => {
            applyView(FIT);
            commitScale();
        },
        handlers: {onPointerDown, onPointerMove, onPointerUp, onPointerCancel: onPointerUp, onPointerLeave: onPointerUp},
    };
};
