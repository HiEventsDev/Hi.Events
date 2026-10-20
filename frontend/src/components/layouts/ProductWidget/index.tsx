import {useParams} from "react-router";
import '../../../styles/widget/default.scss';
import {useQuery} from "@tanstack/react-query";
import {promoCodeClientPublic} from "../../../api/promo-code.client.ts";
import {getParentOrigin} from "../../../utilites/iframeResize.ts";
import {AxiosError} from "axios";
import {getEventPublicQuery, WIDGET_EVENT_STALE_TIME} from "../../../queries/useGetEventPublic.ts";
import SelectProducts from "../../routes/product-widget/SelectProducts";
import {Fragment, useEffect, useState} from "react";
import {Loader} from "@mantine/core";
import {t} from "@lingui/macro";
import {Event, EventOccurrence} from "../../../types.ts";
import {productSelectionFromConfig, WidgetSection} from "../../../utilites/widgetConfig.ts";
import {useWidgetAppearance, useWidgetRuntime} from "../../widget/useWidgetRuntime.ts";
import {WidgetFrame} from "../../widget/WidgetFrame";
import {preloadHeavySections, WidgetEventHeader, WidgetEventSection, WidgetSectionStack} from "../../widget/WidgetSections";
import {WidgetMessage} from "../../widget/WidgetMessage";

const ProductWidget = () => {
    const {eventId} = useParams();
    const {config, checkoutParams, promoCodeValid: loaderPromoCodeValid, isPreview, notFound} = useWidgetRuntime();
    const [selectedOccurrence, setSelectedOccurrence] = useState<EventOccurrence | undefined>();
    const previewPromoQuery = useQuery({
        queryKey: ['widgetPreviewPromoCode', eventId, config.promo_code],
        queryFn: () => promoCodeClientPublic.validateCode(eventId, config.promo_code ?? ''),
        enabled: isPreview && !!config.promo_code,
        staleTime: WIDGET_EVENT_STALE_TIME,
    });
    const promoCodeValid = isPreview
        ? (config.promo_code ? previewPromoQuery.data?.valid : undefined)
        : loaderPromoCodeValid;

    useEffect(() => {
        if (!config.isModal) {
            return;
        }
        const closeOnEscape = (keyboardEvent: KeyboardEvent) => {
            const insideDialog = keyboardEvent.target instanceof Element && !!keyboardEvent.target.closest('[role="dialog"]');
            if (keyboardEvent.key === 'Escape' && !keyboardEvent.defaultPrevented && !insideDialog) {
                window.parent.postMessage({type: 'hievents:close-checkout'}, getParentOrigin() || '*');
            }
        };
        window.addEventListener('keydown', closeOnEscape);
        return () => window.removeEventListener('keydown', closeOnEscape);
    }, [config.isModal]);

    const eventQuery = useQuery<Event, AxiosError>({
        ...getEventPublicQuery(
            eventId,
            promoCodeValid ? config.promo_code ?? null : null,
            !!promoCodeValid,
            config.occurrence_id ?? null,
            productSelectionFromConfig(config),
        ),
        staleTime: WIDGET_EVENT_STALE_TIME,
        enabled: !notFound,
    });
    const event = eventQuery.data;
    const appearance = useWidgetAppearance(config, event?.settings?.homepage_theme_settings);
    preloadHeavySections(config.sections);

    if (notFound || eventQuery.isError || (eventQuery.isFetched && !event)) {
        return (
            <WidgetFrame style={appearance.style} mode={appearance.mode}>
                <WidgetMessage
                    title={t`This event is not available`}
                    description={t`It may have been unpublished or removed. Please check the link and try again.`}
                />
            </WidgetFrame>
        );
    }

    if (!event) {
        return (
            <WidgetFrame style={appearance.style} mode={appearance.mode}>
                <div className={'hi-widget-loading'}>
                    <Loader color={appearance.colors.primaryText} size="md" type="dots"/>
                </div>
            </WidgetFrame>
        );
    }

    const sections: WidgetSection[] = config.sections?.length ? config.sections : ['tickets'];
    const orderedSections = sections.includes('tickets') ? sections : [...sections, 'tickets' as const];
    const showsHeader = orderedSections.includes('header');

    const tickets = (
        <SelectProducts
            key={`${config.products?.join(',')}|${config.categories?.join(',')}|${config.occurrence_id ?? ''}|${promoCodeValid ? config.promo_code : ''}`}
            widgetMode={isPreview ? 'preview' : 'embedded'}
            checkoutMode={config.checkout === 'new-tab' ? 'new-tab' : 'modal'}
            event={event}
            colors={appearance.colors}
            continueButtonText={config.continue_button_text || t`Continue`}
            padding={'0px'}
            showLineup={!!config.show_lineup}
            reportsHeight={false}
            showPoweredBy={false}
            hidePromoCode={!!config.hide_promo_code}
            hideCategoryTitles={!!config.hide_category_titles}
            promoCode={config.promo_code}
            promoCodeValid={promoCodeValid}
            initialOccurrenceId={config.occurrence_id}
            onSelectedOccurrenceChange={setSelectedOccurrence}
            productSelection={productSelectionFromConfig(config)}
            widgetId={config.widgetId}
            checkoutParams={checkoutParams}
        />
    );

    return (
        <WidgetFrame style={appearance.style} mode={appearance.mode} testId="ticket-widget">
            <WidgetSectionStack>
                {config.isModal && !showsHeader && (
                    <WidgetEventHeader event={event} occurrence={selectedOccurrence} reserveCloseButton compact/>
                )}
                {orderedSections.map((section, index) => (
                    <Fragment key={section}>
                        {section === 'tickets'
                            ? tickets
                            : (
                                <WidgetEventSection
                                    section={section}
                                    event={event}
                                    occurrence={selectedOccurrence}
                                    reserveCloseButton={config.isModal && index === 0}
                                />
                            )}
                    </Fragment>
                ))}
            </WidgetSectionStack>
        </WidgetFrame>
    );
};

export default ProductWidget;
