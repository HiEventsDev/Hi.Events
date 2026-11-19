import {ReactNode} from "react";
import {Badge} from "@mantine/core";

export type StatusTone = 'positive' | 'active' | 'waiting' | 'negative' | 'neutral';

const COLORS: Record<StatusTone, string> = {
    positive: 'teal',
    active: 'blue',
    waiting: 'orange',
    negative: 'red',
    neutral: 'gray',
};

export const StatusBadge = ({tone, className, children}: { tone: StatusTone, className?: string, children: ReactNode }) => (
    <Badge variant="light" color={COLORS[tone]} size="sm" radius="sm" tt="none" className={className} style={{flexShrink: 0, minWidth: 'max-content'}}>
        {children}
    </Badge>
);
