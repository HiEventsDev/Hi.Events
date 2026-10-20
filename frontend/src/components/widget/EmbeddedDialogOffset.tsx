import {createContext, ReactNode, useCallback, useContext, useRef} from "react";

const EDGE_GAP = 16;
const LEAD_ABOVE_CLICK = 96;
const MAX_DIALOG_HEIGHT = 640;

export interface EmbeddedDialogPlacement {
    yOffset: number;
    maxHeight: number;
}

const EmbeddedDialogPlacementContext = createContext<(() => EmbeddedDialogPlacement) | null>(null);

export const useEmbeddedDialogPlacement = (): EmbeddedDialogPlacement | undefined => {
    const getPlacement = useContext(EmbeddedDialogPlacementContext);
    return getPlacement ? getPlacement() : undefined;
};

export const embeddedDialogStyles = (placement: EmbeddedDialogPlacement | undefined) => placement
    ? {content: {maxHeight: `${placement.maxHeight}px`}}
    : undefined;

export const EmbeddedDialogOffsetProvider = ({children}: { children: (handlers: {
    onPointerDownCapture: (event: React.PointerEvent) => void;
    onFocusCapture: (event: React.FocusEvent) => void;
}) => ReactNode }) => {
    const lastY = useRef(0);

    const getPlacement = useCallback((): EmbeddedDialogPlacement => {
        const viewportHeight = typeof window === 'undefined' ? MAX_DIALOG_HEIGHT : window.innerHeight;
        const lowestOffset = Math.max(EDGE_GAP, viewportHeight - MAX_DIALOG_HEIGHT - EDGE_GAP);
        const yOffset = Math.min(Math.max(EDGE_GAP, lastY.current - LEAD_ABOVE_CLICK), lowestOffset);

        return {
            yOffset,
            maxHeight: Math.min(MAX_DIALOG_HEIGHT, viewportHeight - yOffset - EDGE_GAP),
        };
    }, []);

    return (
        <EmbeddedDialogPlacementContext.Provider value={getPlacement}>
            {children({
                onPointerDownCapture: (event) => {
                    lastY.current = event.clientY;
                },
                onFocusCapture: (event) => {
                    lastY.current = (event.target as HTMLElement).getBoundingClientRect?.().top ?? lastY.current;
                },
            })}
        </EmbeddedDialogPlacementContext.Provider>
    );
};
