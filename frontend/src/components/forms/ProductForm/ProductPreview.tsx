import {useMemo} from "react";
import {t} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {useDebouncedValue} from "@mantine/hooks";
import {useParams} from "react-router";
import classNames from "classnames";
import {Event, Product} from "../../../types.ts";
import {useGetEventSettings} from "../../../queries/useGetEventSettings.ts";
import {useGetTaxesAndFees} from "../../../queries/useGetTaxesAndFees.ts";
import {nowInTimezone} from "../../../utilites/dates.ts";
import {computeThemeVariables, validateThemeSettings} from "../../../utilites/themeUtils.ts";
import {buildPreviewEvent} from "./buildPreviewEvent.ts";
import {computeVisibilityStatus} from "./visibilityStatus.ts";
import SelectProducts from "../../routes/product-widget/SelectProducts";
import classes from "./ProductPreview.module.scss";

interface ProductPreviewProps {
    form: UseFormReturnType<Product>;
    event?: Event;
}

interface ProductVisibilityStatusLineProps extends ProductPreviewProps {
    dataTestId?: string;
}

export const ProductVisibilityStatusLine = ({form, event, dataTestId}: ProductVisibilityStatusLineProps) => {
    if (!event) {
        return null;
    }

    const status = computeVisibilityStatus(form.values, nowInTimezone(event.timezone));

    return (
        <div
            className={classNames(classes.statusLine, classes[status.level])}
            aria-live="polite"
            data-testid={dataTestId}
        >
            <span className={classes.statusDot}/>
            <span>{status.message}</span>
        </div>
    );
};

export const ProductPreview = ({form, event}: ProductPreviewProps) => {
    const {eventId} = useParams();
    const {data: eventSettings} = useGetEventSettings(eventId);
    const {data: taxesAndFees} = useGetTaxesAndFees();
    const [debouncedValues] = useDebouncedValue(form.values, 200);

    const previewEvent = useMemo(() => {
        if (!event) {
            return undefined;
        }

        return buildPreviewEvent(
            event,
            debouncedValues,
            nowInTimezone(event.timezone),
            taxesAndFees?.data,
            eventSettings ?? event.settings,
        );
    }, [event, debouncedValues, taxesAndFees, eventSettings]);

    const themeSettings = useMemo(
        () => validateThemeSettings((eventSettings ?? event?.settings)?.homepage_theme_settings),
        [eventSettings, event],
    );
    const themeVariables = useMemo(() => computeThemeVariables(themeSettings), [themeSettings]);

    if (!event || !previewEvent) {
        return null;
    }

    const isHiddenFromEveryone = computeVisibilityStatus(form.values, nowInTimezone(event.timezone)).level === 'hidden';

    return (
        <div className={classes.preview}>
            <div className={classes.eyebrow}>{t`Live preview`}</div>
            <div
                className={classes.widgetWrap}
                data-hidden={isHiddenFromEveryone || undefined}
                aria-hidden
            >
                <SelectProducts
                    key={JSON.stringify(debouncedValues)}
                    event={previewEvent}
                    widgetMode="preview"
                    showPoweredBy={false}
                    colors={{
                        background: '#ffffff',
                        primary: themeSettings.accent,
                        primaryText: '#1a1a1a',
                        secondary: themeSettings.accent,
                        secondaryText: themeVariables['--theme-accent-contrast'],
                    }}
                />
            </div>
            <ProductVisibilityStatusLine form={form} event={event} dataTestId="product-visibility-status"/>
            <div className={classes.footnote}>{t`Updates as you type.`}</div>
        </div>
    );
};
