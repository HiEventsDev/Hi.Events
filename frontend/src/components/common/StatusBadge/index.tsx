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

interface StatusBadgeProps {
    tone: StatusTone;
    className?: string;
    children: ReactNode;
}

export const StatusBadge = ({tone, className, children}: StatusBadgeProps) => (
    <Badge
        variant="light"
        color={COLORS[tone]}
        size="md"
        radius="sm"
        tt="none"
        className={className}
        fw={600}
        style={{flexShrink: 0, minWidth: 'max-content'}}
    >
        {children}
    </Badge>
);
