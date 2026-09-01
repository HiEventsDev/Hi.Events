import {ReactNode} from "react";
import {AreaChart} from "@mantine/charts";
import {t} from "@lingui/macro";
import {Card} from "../Card";
import {EventDailyStats} from "../../../types.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {formatDateWithLocale} from "../../../utilites/dates.ts";
import classes from "./StatsCharts.module.scss";

interface StatsChartCardProps {
    dailyStats: EventDailyStats[] | undefined;
    timezone: string;
    dateRangeLabel: string;
    syncId: string;
}

const responsiveChartProps = {
    className: classes.chart,
    dataKey: "date",
    withLegend: true,
    legendProps: {verticalAlign: 'bottom' as const},
    styles: {legend: {justifyContent: 'center', rowGap: 4, paddingTop: 24}},
    xAxisProps: {minTickGap: 24},
    yAxisProps: {width: 'auto' as const, tickMargin: 10, tick: {fontSize: 12, fill: 'currentColor'}},
    tickLine: "none" as const,
};

const ChartCard = ({title, dateRangeLabel, children}: {
    title: string;
    dateRangeLabel: string;
    children: ReactNode;
}) => (
    <Card className={classes.chartCard}>
        <div className={classes.chartCardTitle}>
            <h2>{title}</h2>
            {dateRangeLabel && <span className={classes.dateRange}>{dateRangeLabel}</span>}
        </div>
        {children}
    </Card>
);

export const ProductSalesChartCard = ({dailyStats, timezone, dateRangeLabel, syncId}: StatsChartCardProps) => (
    <ChartCard title={t`Product Sales`} dateRangeLabel={dateRangeLabel}>
        <AreaChart
            {...responsiveChartProps}
            data={dailyStats?.map(stat => ({
                date: formatDateWithLocale(stat.date, 'chartDate', timezone),
                orders_created: stat.orders_created,
                products_sold: stat.products_sold,
                attendees_registered: stat.attendees_registered,
            })) || []}
            series={[
                {name: 'orders_created', color: 'blue.6', label: t`Completed Orders`},
                {name: 'products_sold', color: 'blue.2', label: t`Products Sold`},
                {name: 'attendees_registered', color: 'blue.4', label: t`Attendees Registered`},
            ]}
            curveType="bump"
            areaChartProps={{syncId}}
        />
    </ChartCard>
);

export const RevenueChartCard = ({dailyStats, timezone, dateRangeLabel, syncId, currency}: StatsChartCardProps & {
    currency: string;
}) => (
    <ChartCard title={t`Revenue`} dateRangeLabel={dateRangeLabel}>
        <AreaChart
            {...responsiveChartProps}
            data={dailyStats?.map(stat => ({
                date: formatDateWithLocale(stat.date, 'chartDate', timezone),
                total_fees: stat.total_fees,
                total_sales_gross: stat.total_sales_gross,
                total_tax: stat.total_tax,
                total_refunded: stat.total_refunded,
            })) || []}
            valueFormatter={(value) => formatCurrency(value, currency)}
            series={[
                {name: 'total_fees', label: t`Total Fees`, color: 'primary.3'},
                {name: 'total_sales_gross', label: t`Gross Sales`, color: 'grape.5'},
                {name: 'total_tax', label: t`Total Tax`, color: 'grape.7'},
                {name: 'total_refunded', label: t`Total Refunded`, color: 'red.6'},
            ]}
            curveType="natural"
            areaChartProps={{syncId}}
        />
    </ChartCard>
);
