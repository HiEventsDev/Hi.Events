import {useEffect} from "react";
import {useLocation, useParams, useSearchParams} from "react-router";
import {Loader} from "@mantine/core";
import {t} from "@lingui/macro";
import '../../../../../styles/widget/default.scss';
import {useGetEventPublic} from "../../../../../queries/useGetEventPublic.ts";
import {useGetPromoCodePublic} from "../../../../../queries/useGetPromoCodePublic.ts";
import {getParentOrigin} from "../../../../../utilites/iframeResize.ts";
import SelectProducts from "../../../../../components/routes/product-widget/SelectProducts";
import {widgetColorsFromSearchParams} from "../../../../../utilites/widgetColors.ts";
import {forwardedTrackingParams, WIDGET_PARAM} from "../../../../../utilites/widgetConfig.ts";
import {embedLocaleFromUrl, embedLocaleParam} from "../../../../../utilites/embedLocale.ts";
import {WidgetMessage} from "../../../../../components/widget/WidgetMessage";

const SeatCheckout = () => {
    const {eventId} = useParams();
    const [searchParams] = useSearchParams();
    const location = useLocation();
    const occurrenceId = Number(searchParams.get('occurrence_id')) || null;
    const promoCode = searchParams.get('promo_code');
    const promoCodeQuery = useGetPromoCodePublic(eventId, promoCode);
    const isPromoCodeResolved = promoCode === null || promoCodeQuery.isFetched;
    const promoCodeValid = promoCode === null ? undefined : promoCodeQuery.data?.valid === true;
    const eventQuery = useGetEventPublic(
        eventId,
        isPromoCodeResolved,
        promoCodeValid === true,
        promoCodeValid ? promoCode : null,
        occurrenceId,
    );
    const event = eventQuery.data;
    const colors = widgetColorsFromSearchParams(searchParams);

    useEffect(() => {
        const parentOrigin = getParentOrigin();
        const onMessage = (message: MessageEvent) => {
            if (parentOrigin && message.origin !== parentOrigin) return;
            if (message.data?.type === 'hievents:request-close') {
                window.parent.postMessage({type: 'hievents:close-checkout'}, parentOrigin || '*');
            }
        };
        window.addEventListener('message', onMessage);
        return () => window.removeEventListener('message', onMessage);
    }, []);

    if (eventQuery.isError) {
        return (
            <div className={'hi-widget-tokens'}>
                <WidgetMessage
                    title={t`This event is not available`}
                    description={t`It may have been unpublished or removed. Please check the link and try again.`}
                />
            </div>
        );
    }

    if (!event || !isPromoCodeResolved) {
        return (
            <div className={'hi-widget-loading'} data-testid="seat-checkout-loading">
                <Loader color={colors.primary} size="md" type="dots"/>
            </div>
        );
    }

    return (
        <SelectProducts event={event} widgetMode="normal" isSeatPickerPage initialOccurrenceId={occurrenceId}
                        colors={colors}
                        promoCode={promoCodeValid ? promoCode ?? undefined : undefined}
                        promoCodeValid={promoCodeValid}
                        widgetId={searchParams.get(WIDGET_PARAM.widget)}
                        checkoutParams={{
                            ...forwardedTrackingParams(searchParams),
                            ...embedLocaleParam(embedLocaleFromUrl(location.pathname, location.search)),
                        }}/>
    );
};

export default SeatCheckout;
