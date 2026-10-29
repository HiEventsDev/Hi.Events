import {useLayoutEffect, useRef, useState} from "react";
import {getBadgeDimensions, MM_TO_PX} from "../../../utilites/badges.ts";
import {NameBadge, NameBadgeProps} from "./index.tsx";
import classes from "./ScaledNameBadge.module.scss";

interface ScaledNameBadgeProps extends NameBadgeProps {
    maxScale?: number;
}

export const ScaledNameBadge = ({maxScale = 1.6, ...badgeProps}: ScaledNameBadgeProps) => {
    const containerRef = useRef<HTMLDivElement>(null);
    const [scale, setScale] = useState(1);
    const {width, height} = getBadgeDimensions(badgeProps.layout);
    const widthPx = width * MM_TO_PX;
    const heightPx = height * MM_TO_PX;

    useLayoutEffect(() => {
        const container = containerRef.current;
        if (!container) {
            return;
        }

        const update = () => {
            const availableWidth = container.clientWidth;
            const availableHeight = container.clientHeight || Number.POSITIVE_INFINITY;
            if (availableWidth === 0) {
                return;
            }
            setScale(Math.min(availableWidth / widthPx, availableHeight / heightPx, maxScale));
        };

        update();
        const observer = new ResizeObserver(update);
        observer.observe(container);
        return () => observer.disconnect();
    }, [widthPx, heightPx, maxScale]);

    return (
        <div ref={containerRef} className={classes.container}>
            <div className={classes.frame} style={{width: widthPx * scale, height: heightPx * scale}}>
                <div className={classes.scaler} style={{transform: `scale(${scale})`}}>
                    <NameBadge {...badgeProps}/>
                </div>
            </div>
        </div>
    );
};
