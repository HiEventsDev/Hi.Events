import {createContext, ReactNode, RefObject, useCallback, useContext, useEffect, useMemo, useRef, useState} from "react";
import {EmbeddedDialogPlacement, placeEmbeddedDialog, VisibleBand} from "./embeddedDialogPlacement.ts";

interface OpenDialogs {
    anchorY: number;
    placement: EmbeddedDialogPlacement;
    listeners: Set<(placement: EmbeddedDialogPlacement) => void>;
}

interface DialogRegistry {
    place: () => EmbeddedDialogPlacement;
    open: (listener: (placement: EmbeddedDialogPlacement) => void) => () => void;
}

const EmbeddedDialogRegistryContext = createContext<DialogRegistry | null>(null);

const placeAt = (anchorY: number, band: VisibleBand | null) =>
    placeEmbeddedDialog(anchorY, typeof window === 'undefined' ? 0 : window.innerHeight, band);

export const useEmbeddedDialogPlacement = (): EmbeddedDialogPlacement | undefined => {
    const registry = useContext(EmbeddedDialogRegistryContext);
    const [placement, setPlacement] = useState(() => registry?.place());

    useEffect(() => registry?.open(setPlacement), [registry]);

    return placement;
};

export const embeddedDialogStyles = (placement: EmbeddedDialogPlacement | undefined) => placement
    ? {content: {maxHeight: `${placement.maxHeight}px`}}
    : undefined;

export interface FrameBindings {
    ref: RefObject<HTMLElement | null>;
    minHeight: number;
    onPointerDownCapture: (event: React.PointerEvent) => void;
    onFocusCapture: (event: React.FocusEvent) => void;
}

export const EmbeddedDialogOffsetProvider = ({children}: { children: (frame: FrameBindings) => ReactNode }) => {
    const frameRef = useRef<HTMLElement>(null);
    const anchorY = useRef(0);
    const band = useRef<VisibleBand | null>(null);
    const openDialogs = useRef<OpenDialogs | null>(null);
    const [minHeight, setMinHeight] = useState(0);

    const replace = useCallback(() => {
        const current = openDialogs.current;
        if (!current) {
            return;
        }
        const placement = placeAt(current.anchorY, band.current);
        current.placement = placement;
        current.listeners.forEach(listener => listener(placement));
        setMinHeight(height => Math.max(height, placement.frameHeight));
    }, []);

    const measure = useCallback(() => {
        const frame = frameRef.current;
        if (!frame || typeof IntersectionObserver === 'undefined') {
            replace();
            return;
        }
        const observer = new IntersectionObserver(([entry]) => {
            observer.disconnect();
            const {top, bottom} = entry.intersectionRect;
            band.current = bottom > top ? {top, bottom} : null;
            replace();
        });
        observer.observe(frame);
    }, [replace]);

    useEffect(() => {
        const onResize = () => {
            if (openDialogs.current) {
                measure();
            }
        };
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
    }, [measure]);

    const registry = useMemo<DialogRegistry>(() => ({
        place: () => openDialogs.current?.placement ?? placeAt(anchorY.current, band.current),
        open: (listener) => {
            if (!openDialogs.current) {
                const placement = placeAt(anchorY.current, band.current);
                openDialogs.current = {anchorY: anchorY.current, placement, listeners: new Set()};
                setMinHeight(placement.frameHeight);
                listener(placement);
            }
            const current = openDialogs.current;
            current.listeners.add(listener);

            return () => {
                current.listeners.delete(listener);
                queueMicrotask(() => {
                    if (openDialogs.current === current && current.listeners.size === 0) {
                        openDialogs.current = null;
                        setMinHeight(0);
                    }
                });
            };
        },
    }), []);

    const startFrom = (y: number) => {
        if (openDialogs.current) {
            return;
        }
        anchorY.current = y;
        measure();
    };

    return (
        <EmbeddedDialogRegistryContext.Provider value={registry}>
            {children({
                ref: frameRef,
                minHeight,
                onPointerDownCapture: (event) => startFrom(event.clientY),
                onFocusCapture: (event) => startFrom((event.target as HTMLElement).getBoundingClientRect?.().top ?? anchorY.current),
            })}
        </EmbeddedDialogRegistryContext.Provider>
    );
};
