const EDGE_GAP = 16;
const LEAD_ABOVE_CLICK = 96;
const MAX_DIALOG_HEIGHT = 640;
const MIN_DIALOG_HEIGHT = 200;

export interface VisibleBand {
    top: number;
    bottom: number;
}

export interface EmbeddedDialogPlacement {
    yOffset: number;
    maxHeight: number;
    frameHeight: number;
}

export const placeEmbeddedDialog = (anchorY: number, viewportHeight: number, band: VisibleBand | null): EmbeddedDialogPlacement => {
    const visibleTop = Math.max(0, band?.top ?? 0);
    const visibleBottom = Math.min(viewportHeight, band?.bottom ?? viewportHeight);
    const maxHeight = Math.max(0, Math.min(
        MAX_DIALOG_HEIGHT,
        viewportHeight - 2 * EDGE_GAP,
        Math.max(MIN_DIALOG_HEIGHT, visibleBottom - visibleTop - 2 * EDGE_GAP),
    ));
    const preferredTop = Math.max(visibleTop + EDGE_GAP, anchorY - LEAD_ABOVE_CLICK);
    const topWithinBand = Math.max(visibleTop + EDGE_GAP, Math.min(preferredTop, visibleBottom - EDGE_GAP - maxHeight));
    const frameCanGrowIntoView = visibleBottom >= viewportHeight - 1;

    return {
        yOffset: Math.max(EDGE_GAP, Math.min(topWithinBand, viewportHeight - EDGE_GAP - maxHeight)),
        maxHeight,
        frameHeight: maxHeight < MAX_DIALOG_HEIGHT && frameCanGrowIntoView
            ? preferredTop + MAX_DIALOG_HEIGHT + EDGE_GAP
            : 0,
    };
};
