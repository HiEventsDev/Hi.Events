import {LoaderFunctionArgs} from "react-router";
import {AxiosError} from "axios";
import {validatePromoCode} from "./validatePromoCode.ts";
import {getEventPublicQuery} from "../queries/useGetEventPublic.ts";
import {getEventLineupPublicQuery} from "../queries/useGetEventLineupPublic.ts";
import {getWidgetPublicQuery} from "../queries/useGetWidgetPublic.ts";
import {getOrganizerPublicQuery} from "../queries/useGetOrganizerPublic.ts";
import {organizerWidgetEventsQuery} from "../queries/useGetOrganizerWidgetEvents.ts";
import {getQueryClient} from "../utilites/ssrQueryClient.ts";
import {getSsrRequestContext} from "../utilites/ssrRequestContext.ts";
import {
    productSelectionFromConfig,
    resolveWidgetConfig,
    WIDGET_PARAM,
    WidgetRuntimeConfig,
    WidgetSettings
} from "../utilites/widgetConfig.ts";
import {loggableError} from "../ssr/loggableError.js";

export interface WidgetLoaderData {
    preset: WidgetSettings | null;
    promoCode: string | null;
    promoCodeValid: boolean | undefined;
    notFound: boolean;
}

const isNotFound = (error: unknown) => (error as AxiosError | undefined)?.response?.status === 404;

const loadPreset = async (search: URLSearchParams): Promise<WidgetSettings | null> => {
    const widgetId = search.get(WIDGET_PARAM.widget);
    if (!widgetId) {
        return null;
    }
    try {
        const widget = await getQueryClient().ensureQueryData(getWidgetPublicQuery(widgetId));
        return widget.settings;
    } catch {
        return null;
    }
};

const ticketEventQueryArgs = (eventId: string | undefined, config: WidgetRuntimeConfig, promoCodeValid: boolean | undefined) => [
    eventId,
    promoCodeValid ? config.promo_code ?? null : null,
    !!promoCodeValid,
    config.occurrence_id ?? null,
    productSelectionFromConfig(config),
] as const;

const configFor = async (request: Request) => {
    const search = new URL(request.url).searchParams;
    const preset = await loadPreset(search);
    const ssrContext = getSsrRequestContext();
    if (ssrContext && preset?.locale) {
        ssrContext.widgetLocale = preset.locale;
    }
    return {preset, config: resolveWidgetConfig(search, preset)};
};

const wantsLineup = (config: WidgetSettings) => !!config.show_lineup
    || !!config.sections?.some(section => section === 'lineup' || section === 'schedule');

export const ticketWidgetRouteLoader = async ({params, request}: LoaderFunctionArgs): Promise<WidgetLoaderData> => {
    const urlPromoCode = resolveWidgetConfig(new URL(request.url).searchParams).promo_code ?? null;
    const [{preset, config}, urlPromoCodeValid] = await Promise.all([
        configFor(request),
        urlPromoCode ? validatePromoCode(params.eventId, urlPromoCode) : Promise.resolve(undefined),
    ]);
    const promoCode = config.promo_code ?? null;

    try {
        const promoCodeValid = promoCode === urlPromoCode
            ? urlPromoCodeValid
            : promoCode ? await validatePromoCode(params.eventId, promoCode) : undefined;
        await Promise.all([
            getQueryClient().ensureQueryData(getEventPublicQuery(...ticketEventQueryArgs(params.eventId, config, promoCodeValid))),
            wantsLineup(config) ? getQueryClient().prefetchQuery(getEventLineupPublicQuery(params.eventId)) : null,
        ]);
        return {preset, promoCode, promoCodeValid, notFound: false};
    } catch (error) {
        if (!isNotFound(error)) {
            console.error(loggableError(error));
        }
        return {preset, promoCode, promoCodeValid: undefined, notFound: isNotFound(error)};
    }
};

export const eventContentWidgetRouteLoader = async ({params, request}: LoaderFunctionArgs): Promise<WidgetLoaderData> => {
    const {preset, config} = await configFor(request);

    try {
        await Promise.all([
            getQueryClient().ensureQueryData(getEventPublicQuery(params.eventId, null, false, config.occurrence_id ?? null)),
            getQueryClient().prefetchQuery(getEventLineupPublicQuery(params.eventId)),
        ]);
        return {preset, promoCode: null, promoCodeValid: undefined, notFound: false};
    } catch (error) {
        if (!isNotFound(error)) {
            console.error(loggableError(error));
        }
        return {preset, promoCode: null, promoCodeValid: undefined, notFound: isNotFound(error)};
    }
};

export const organizerWidgetRouteLoader = async ({params, request}: LoaderFunctionArgs): Promise<WidgetLoaderData> => {
    const {preset, config} = await configFor(request);

    try {
        await Promise.all([
            getQueryClient().prefetchQuery(getOrganizerPublicQuery(params.organizerId)),
            getQueryClient().fetchInfiniteQuery(organizerWidgetEventsQuery(String(params.organizerId), config, 'upcoming')),
        ]);
        return {preset, promoCode: null, promoCodeValid: undefined, notFound: false};
    } catch (error) {
        if (!isNotFound(error)) {
            console.error(loggableError(error));
        }
        return {preset, promoCode: null, promoCodeValid: undefined, notFound: isNotFound(error)};
    }
};
