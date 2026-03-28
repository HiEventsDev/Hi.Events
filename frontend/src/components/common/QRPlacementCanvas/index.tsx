import {useEffect, useRef, useState} from 'react';

export interface QRPlacementValue {
    qr_x: number;
    qr_y: number;
    qr_size: number;
    num_x: number | null;
    num_y: number | null;
}

interface QRPlacementCanvasProps {
    templateImageUrl: string;
    value: QRPlacementValue;
    onChange: (v: QRPlacementValue) => void;
}

type DragTarget = 'qr' | 'counter' | 'qr-resize' | null;

export const QRPlacementCanvas = ({templateImageUrl, value, onChange}: QRPlacementCanvasProps) => {
    const imgRef = useRef<HTMLImageElement>(null);
    const [imageLoaded, setImageLoaded] = useState(false);
    const dragRef = useRef<{
        target: DragTarget;
        startMouseX: number;
        startMouseY: number;
        startValue: QRPlacementValue;
    } | null>(null);

    // Returns scale factors (native px per display px) and image offset within container
    const getScale = () => {
        const img = imgRef.current;
        if (!img || !imageLoaded) return {sx: 1, sy: 1, offsetLeft: 0, offsetTop: 0};
        return {
            sx: img.naturalWidth / img.clientWidth,
            sy: img.naturalHeight / img.clientHeight,
            offsetLeft: img.offsetLeft,
            offsetTop: img.offsetTop,
        };
    };

    const toDisplay = (nativePx: number, scale: number) => nativePx / scale;

    const startDrag = (e: React.MouseEvent, target: DragTarget) => {
        e.preventDefault();
        dragRef.current = {
            target,
            startMouseX: e.clientX,
            startMouseY: e.clientY,
            startValue: {...value},
        };
    };

    useEffect(() => {
        const onMove = (e: MouseEvent) => {
            if (!dragRef.current) return;
            const {target, startMouseX, startMouseY, startValue} = dragRef.current;
            const {sx, sy} = getScale();
            const img = imgRef.current;
            if (!img) return;

            const dx = Math.round((e.clientX - startMouseX) * sx);
            const dy = Math.round((e.clientY - startMouseY) * sy);
            const maxX = img.naturalWidth;
            const maxY = img.naturalHeight;
            const clamp = (v: number, lo: number, hi: number) => Math.max(lo, Math.min(hi, v));

            if (target === 'qr') {
                onChange({
                    ...startValue,
                    qr_x: clamp(startValue.qr_x + dx, 0, maxX - startValue.qr_size),
                    qr_y: clamp(startValue.qr_y + dy, 0, maxY - startValue.qr_size),
                });
            } else if (target === 'qr-resize') {
                const newSize = clamp(startValue.qr_size + dx, 20, Math.min(maxX, maxY) / 2);
                onChange({
                    ...startValue,
                    qr_size: newSize,
                    qr_x: clamp(startValue.qr_x, 0, maxX - newSize),
                    qr_y: clamp(startValue.qr_y, 0, maxY - newSize),
                });
            } else if (target === 'counter') {
                onChange({
                    ...startValue,
                    num_x: clamp((startValue.num_x ?? 0) + dx, 0, maxX - Math.round(60 * sx)),
                    num_y: clamp((startValue.num_y ?? 0) + dy, 0, maxY - Math.round(24 * sy)),
                });
            }
        };

        const onUp = () => {
            dragRef.current = null;
        };

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
        return () => {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
        };
    }, [value, imageLoaded, onChange]);

    const {sx, sy, offsetLeft, offsetTop} = getScale();

    const qrLeft = offsetLeft + toDisplay(value.qr_x, sx);
    const qrTop = offsetTop + toDisplay(value.qr_y, sy);
    const qrDisplaySize = toDisplay(value.qr_size, sx);
    const numLeft = value.num_x !== null ? offsetLeft + toDisplay(value.num_x, sx) : 0;
    const numTop = value.num_y !== null ? offsetTop + toDisplay(value.num_y, sy) : 0;

    return (
        <div style={{position: 'relative', display: 'inline-block', userSelect: 'none'}}>
            <img
                ref={imgRef}
                src={templateImageUrl}
                alt="Ticket template"
                style={{display: 'block', maxWidth: '100%'}}
                onLoad={() => setImageLoaded(true)}
            />

            {imageLoaded && (
                <>
                    {/* QR placement box — yellow, draggable + resizable */}
                    <div
                        onMouseDown={(e) => startDrag(e, 'qr')}
                        style={{
                            position: 'absolute',
                            left: qrLeft,
                            top: qrTop,
                            width: qrDisplaySize,
                            height: qrDisplaySize,
                            border: '2px solid #f59e0b',
                            background: 'rgba(251,191,36,0.15)',
                            cursor: 'move',
                            boxSizing: 'border-box',
                        }}
                    >
                        {/* Resize handle */}
                        <div
                            onMouseDown={(e) => {
                                e.stopPropagation();
                                startDrag(e, 'qr-resize');
                            }}
                            style={{
                                position: 'absolute',
                                right: -5,
                                bottom: -5,
                                width: 10,
                                height: 10,
                                background: '#f59e0b',
                                cursor: 'se-resize',
                            }}
                        />
                    </div>

                    {/* Counter placement box — green dashed, drag-only */}
                    {value.num_x !== null && value.num_y !== null && (
                        <div
                            onMouseDown={(e) => startDrag(e, 'counter')}
                            style={{
                                position: 'absolute',
                                left: numLeft,
                                top: numTop,
                                width: 60,
                                height: 24,
                                border: '2px dashed #22c55e',
                                background: 'rgba(34,197,94,0.1)',
                                cursor: 'move',
                                boxSizing: 'border-box',
                                fontSize: 10,
                                color: '#166534',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            001
                        </div>
                    )}
                </>
            )}
        </div>
    );
};
