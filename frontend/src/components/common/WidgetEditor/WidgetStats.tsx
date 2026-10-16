import {t} from "@lingui/macro";
import {Widget} from "../../../api/widget.client.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import classes from './WidgetEditor.module.scss';

export const WidgetStats = ({widget}: { widget: Widget }) => {
    const {views, orders, revenue} = widget.stats;
    const conversion = views > 0 ? Math.round((orders / views) * 1000) / 10 : null;
    const revenueLabel = revenue && Object.keys(revenue).length
        ? Object.entries(revenue).map(([currency, amount]) => formatCurrency(amount, currency)).join(' · ')
        : null;

    return (
        <dl className={classes.stats} data-testid="widget-stats">
            <div><dt>{t`Views`}</dt><dd>{views.toLocaleString()}</dd></div>
            <div><dt>{t`Orders`}</dt><dd>{orders.toLocaleString()}</dd></div>
            <div><dt>{t`Conversion`}</dt><dd>{conversion === null ? '—' : `${conversion}%`}</dd></div>
            {revenue !== undefined && <div><dt>{t`Revenue`}</dt><dd>{revenueLabel ?? '—'}</dd></div>}
        </dl>
    );
};
