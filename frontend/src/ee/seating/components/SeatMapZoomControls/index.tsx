import {ActionIcon} from "@mantine/core";
import {IconFocusCentered, IconMinus, IconPlus} from "@tabler/icons-react";
import {t} from "@lingui/macro";

interface SeatMapZoomControlsProps {
    zoomIn: () => void;
    zoomOut: () => void;
    reset: () => void;
    large?: boolean;
    className?: string;
}

export const SeatMapZoomControls = ({zoomIn, zoomOut, reset, large = false, className}: SeatMapZoomControlsProps) => {
    const size = large ? 'lg' : undefined;
    const iconSize = large ? 18 : 16;

    return (
        <div className={className}>
            <ActionIcon variant="default" size={size} onClick={zoomIn} aria-label={t`Zoom in`}><IconPlus size={iconSize}/></ActionIcon>
            <ActionIcon variant="default" size={size} onClick={zoomOut} aria-label={t`Zoom out`}><IconMinus size={iconSize}/></ActionIcon>
            <ActionIcon variant="default" size={size} onClick={reset} aria-label={t`Reset view`}><IconFocusCentered size={iconSize}/></ActionIcon>
        </div>
    );
};
