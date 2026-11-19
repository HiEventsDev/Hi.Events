import {t} from "@lingui/macro";
import {formatDateWithLocale} from "../../../utilites/dates.ts";
import {CrmSegmentEventOption, CrmSegmentList, CrmSegmentOptions} from "../types.ts";

export const deletedSegmentOptions = (audience: string, segments: CrmSegmentList | undefined) => {
    if (!segments || !audience.startsWith('saved:')) {
        return [];
    }

    const segmentId = Number(audience.slice('saved:'.length));

    return segments.data.some(segment => segment.id === segmentId)
        ? []
        : [{group: t`Your segments`, items: [{value: audience, label: t`Deleted segment`}]}];
};

export const eventOptionLabel = (event: CrmSegmentEventOption): string => event.starts_at
    ? `${event.title} · ${formatDateWithLocale(event.starts_at, 'shortDate', event.timezone)}`
    : event.title;

export const productOptionLabel = (product: CrmSegmentOptions['products'][number]): string =>
    `${product.title} · ${product.event_title}`;
