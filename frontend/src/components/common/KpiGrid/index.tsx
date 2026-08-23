import {ReactNode} from "react";
import {Skeleton} from "@mantine/core";
import {Sparkline} from "@mantine/charts";
import {IconArrowDownRight, IconArrowUpRight} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import classes from "./KpiGrid.module.scss";

interface KpiGridProps {
    children: ReactNode;
    className?: string;
}

export const KpiGrid = ({children, className = ''}: KpiGridProps) => {
    return (
        <div className={`${classes.grid} ${className}`}>
            {children}
        </div>
    );
};

export interface KpiCellDelta {
    percent: number;
    trend: 'up' | 'down' | 'flat';
}

interface KpiCellProps {
    label: string;
    value: string | number;
    sparkline?: number[];
    delta?: KpiCellDelta | null;
    isLoading?: boolean;
    testId?: string;
}

const formatPercent = (percent: number, sign: '+' | '-') => {
    const abs = Math.abs(percent).toFixed(1);
    return `${sign}${abs}%`;
};

export const KpiCell = ({label, value, sparkline, delta, isLoading = false, testId}: KpiCellProps) => {
    if (isLoading) {
        return (
            <div className={classes.cell} data-testid={testId}>
                <div className={classes.label}>{label}</div>
                <div className={classes.skeletonRow}>
                    <Skeleton height={20} width={80} radius="sm"/>
                    <Skeleton height={20} width={56} radius="sm"/>
                </div>
                <Skeleton height={12} width={60} radius="sm" mt={8}/>
            </div>
        );
    }

    let deltaContent: ReactNode;
    if (delta == null) {
        deltaContent = <span className={classes.deltaPlaceholder} aria-hidden="true"/>;
    } else if (delta.trend === 'flat') {
        deltaContent = <span className={`${classes.delta} ${classes.deltaFlat}`}>{t`No change`}</span>;
    } else if (delta.trend === 'up') {
        deltaContent = (
            <span className={`${classes.delta} ${classes.deltaUp}`}>
                <IconArrowUpRight size={12} stroke={2}/>
                {formatPercent(delta.percent, '+')}
            </span>
        );
    } else {
        deltaContent = (
            <span className={`${classes.delta} ${classes.deltaDown}`}>
                <IconArrowDownRight size={12} stroke={2}/>
                {formatPercent(delta.percent, '-')}
            </span>
        );
    }

    return (
        <div className={classes.cell} data-testid={testId}>
            <div className={classes.label}>{label}</div>
            <div className={classes.middleRow}>
                <div className={classes.value} data-testid={testId ? `${testId}-value` : undefined}>{value}</div>
                {sparkline && sparkline.length > 0 ? (
                    <Sparkline
                        className={classes.sparkline}
                        w={56}
                        h={20}
                        data={sparkline}
                        color="primary.5"
                        fillOpacity={0}
                        strokeWidth={1.2}
                        curveType="linear"
                        areaProps={{opacity: 0.6}}
                    />
                ) : (
                    <div className={classes.sparklineSpacer}/>
                )}
            </div>
            {deltaContent}
        </div>
    );
};

export default KpiGrid;
