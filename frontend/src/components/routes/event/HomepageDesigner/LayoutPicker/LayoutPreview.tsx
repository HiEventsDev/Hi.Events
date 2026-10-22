import {ReactNode} from "react";
import {EventLayout, HomepageThemeSettings} from "../../../../../types.ts";

interface PreviewColors {
    accent: string;
    background: string;
    surface: string;
    ink: string;
    faint: string;
}

const previewColors = (theme: HomepageThemeSettings): PreviewColors => theme.mode === 'dark'
    ? {accent: theme.accent, background: theme.background, surface: '#1f1f1f', ink: 'rgba(255, 255, 255, 0.85)', faint: 'rgba(255, 255, 255, 0.18)'}
    : {accent: theme.accent, background: theme.background, surface: '#ffffff', ink: 'rgba(26, 26, 26, 0.8)', faint: 'rgba(26, 26, 26, 0.14)'};

const Bar = ({x, y, w, h = 4, fill}: { x: number, y: number, w: number, h?: number, fill: string }) => (
    <rect x={x} y={y} width={w} height={h} rx={h / 2} fill={fill}/>
);

const TicketCard = ({x, y, w, h, colors}: { x: number, y: number, w: number, h: number, colors: PreviewColors }) => (
    <>
        <rect x={x} y={y} width={w} height={h} rx={4} fill={colors.surface} stroke={colors.faint}/>
        <Bar x={x + 4} y={y + 5} w={w * 0.5} fill={colors.faint}/>
        <rect x={x + 4} y={y + h - 11} width={w - 8} height={7} rx={2} fill={colors.accent}/>
    </>
);

const layouts: Record<EventLayout, (colors: PreviewColors) => ReactNode> = {
    MARQUEE: colors => (
        <>
            <rect x={10} y={10} width={140} height={120} rx={6} fill={colors.surface}/>
            <rect x={14} y={14} width={132} height={30} rx={4} fill={colors.accent}/>
            <Bar x={18} y={52} w={56} h={6} fill={colors.ink}/>
            <Bar x={18} y={63} w={40} fill={colors.faint}/>
            <Bar x={18} y={84} w={70} fill={colors.faint}/>
            <Bar x={18} y={92} w={64} fill={colors.faint}/>
            <Bar x={18} y={100} w={68} fill={colors.faint}/>
            <TicketCard x={98} y={52} w={44} h={46} colors={colors}/>
        </>
    ),
    FLYER: colors => (
        <>
            <rect x={10} y={10} width={140} height={120} rx={6} fill={colors.surface}/>
            <rect x={16} y={16} width={50} height={64} rx={4} fill={colors.accent}/>
            <Bar x={16} y={86} w={30} h={6} fill={colors.faint}/>
            <Bar x={74} y={18} w={60} h={6} fill={colors.ink}/>
            <Bar x={74} y={29} w={44} fill={colors.faint}/>
            <TicketCard x={74} y={40} w={66} h={34} colors={colors}/>
            <Bar x={74} y={82} w={60} fill={colors.faint}/>
            <Bar x={74} y={90} w={54} fill={colors.faint}/>
            <Bar x={74} y={98} w={58} fill={colors.faint}/>
        </>
    ),
    STORY: colors => (
        <>
            <rect x={30} y={10} width={100} height={120} rx={6} fill={colors.surface}/>
            <rect x={34} y={14} width={92} height={26} rx={4} fill={colors.accent}/>
            <Bar x={38} y={48} w={50} h={6} fill={colors.ink}/>
            <Bar x={38} y={59} w={36} fill={colors.faint}/>
            <TicketCard x={38} y={70} w={84} h={24} colors={colors}/>
            <Bar x={38} y={102} w={80} fill={colors.faint}/>
            <Bar x={38} y={110} w={70} fill={colors.faint}/>
        </>
    ),
    INVITE: colors => (
        <>
            <rect x={44} y={10} width={72} height={120} rx={6} fill={colors.surface}/>
            <rect x={48} y={14} width={64} height={22} rx={4} fill={colors.accent}/>
            <Bar x={68} y={42} w={24} fill={colors.faint}/>
            <Bar x={58} y={52} w={44} h={6} fill={colors.ink}/>
            <Bar x={64} y={63} w={32} fill={colors.faint}/>
            <TicketCard x={50} y={74} w={60} h={24} colors={colors}/>
            <Bar x={54} y={106} w={52} fill={colors.faint}/>
        </>
    ),
};

export const LayoutPreview = ({layout, theme}: { layout: EventLayout, theme: HomepageThemeSettings }) => {
    const colors = previewColors(theme);

    return (
        <svg viewBox="0 0 160 120" aria-hidden="true" focusable="false">
            <rect width={160} height={120} fill={colors.background}/>
            {layouts[layout](colors)}
        </svg>
    );
};
