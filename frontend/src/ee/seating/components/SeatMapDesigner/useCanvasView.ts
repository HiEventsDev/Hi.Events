import {RefObject, useCallback, useEffect, useRef, useState} from "react";
import {Bounds, Point} from "../lib/geometry.ts";

const MIN_SCALE = 0.15;
const MAX_SCALE = 3;
const FIT_MARGIN = 70;

export interface CanvasView {
    x: number;
    y: number;
    scale: number;
}

type ViewChange = (current: CanvasView) => CanvasView;

const clampScale = (scale: number) => Math.min(MAX_SCALE, Math.max(MIN_SCALE, scale));

export const useCanvasView = (svgRef: RefObject<SVGSVGElement | null>) => {
    const [view, setView] = useState<CanvasView>({x: 0, y: 0, scale: 1});
    const pendingChanges = useRef<ViewChange[]>([]);
    const frame = useRef<number | null>(null);

    const scheduleChange = useCallback((change: ViewChange) => {
        pendingChanges.current.push(change);
        if (frame.current !== null) {
            return;
        }
        frame.current = requestAnimationFrame(() => {
            const changes = pendingChanges.current;
            pendingChanges.current = [];
            frame.current = null;
            setView(current => changes.reduce((next, apply) => apply(next), current));
        });
    }, []);

    useEffect(() => () => {
        if (frame.current !== null) {
            cancelAnimationFrame(frame.current);
        }
    }, []);

    const toLayoutPoint = useCallback((clientX: number, clientY: number): Point => {
        const rect = svgRef.current?.getBoundingClientRect();
        return {
            x: (clientX - (rect?.left ?? 0) - view.x) / view.scale,
            y: (clientY - (rect?.top ?? 0) - view.y) / view.scale,
        };
    }, [svgRef, view]);

    const zoomAt = useCallback((factor: number, clientX?: number, clientY?: number) => {
        const rect = svgRef.current?.getBoundingClientRect();
        if (!rect) {
            return;
        }
        const px = (clientX ?? rect.left + rect.width / 2) - rect.left;
        const py = (clientY ?? rect.top + rect.height / 2) - rect.top;
        scheduleChange(current => {
            const scale = clampScale(current.scale * factor);
            const ratio = scale / current.scale;
            return {scale, x: px - (px - current.x) * ratio, y: py - (py - current.y) * ratio};
        });
    }, [svgRef, scheduleChange]);

    const panBy = useCallback((dx: number, dy: number) => {
        scheduleChange(current => ({...current, x: current.x + dx, y: current.y + dy}));
    }, [scheduleChange]);

    const fit = useCallback((bounds: Bounds) => {
        const rect = svgRef.current?.getBoundingClientRect();
        if (!rect || rect.width === 0) {
            return;
        }
        const scale = clampScale(Math.min((rect.width - FIT_MARGIN) / bounds.width, (rect.height - FIT_MARGIN) / bounds.height));
        pendingChanges.current = [];
        setView({
            scale,
            x: (rect.width - bounds.width * scale) / 2 - bounds.x * scale,
            y: (rect.height - bounds.height * scale) / 2 - bounds.y * scale,
        });
    }, [svgRef]);

    return {view, toLayoutPoint, zoomAt, panBy, fit};
};
