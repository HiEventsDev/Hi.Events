/* eslint-disable lingui/no-unlocalized-strings */
import {Helmet} from "react-helmet-async";
import {Event, LocationType} from "../../../types";
import {eventCoverImageUrl, eventHomepageUrl} from "../../../utilites/urlHelper.ts";
import {utcToTz} from "../../../utilites/dates.ts";
import {resolveEventLocation} from "../../../utilites/effectiveLocation.ts";
import {formatAddress} from "../../../utilites/addressUtilities.ts";

interface EventDocumentHeadProps {
    event: Event;
}

export const EventDocumentHead = ({event}: EventDocumentHeadProps) => {
    const eventSettings = event.settings;
    const products = event.product_categories?.flatMap(category => category.products) ?? [];
    const title = (eventSettings?.seo_title ?? event.title) + ' | ' + event.organizer?.name;
    const description = eventSettings?.seo_description ?? event.description_preview;
    const keywords = eventSettings?.seo_keywords;
    const image = eventCoverImageUrl(event);
    const url = eventHomepageUrl(event);
    const startDate = utcToTz(new Date(event.start_date), event.timezone);
    const endDate = event.end_date ? utcToTz(new Date(event.end_date), event.timezone) : undefined;

    const occurrences = event.occurrences ?? [];
    const resolvedList = occurrences.length > 0
        ? occurrences.map(o => resolveEventLocation(event, o))
        : [resolveEventLocation(event, null)];
    const types = new Set<string>();
    for (const r of resolvedList) {
        if (r) types.add(r.type);
    }
    const effective = resolvedList[0];
    const hasMixedModes = types.size > 1;
    const structuredAddress = effective?.type === LocationType.InPerson ? effective.location?.structured_address : null;

    const address = structuredAddress ? {
        "@type": "http://schema.org/PostalAddress",
        streetAddress: structuredAddress.address_line_1,
        addressLocality: structuredAddress.city,
        addressRegion: structuredAddress.state_or_region,
        postalCode: structuredAddress.zip_or_postal_code,
        addressCountry: structuredAddress.country
    } : null;

    if (address) {
        // eslint-disable-next-line @typescript-eslint/ban-ts-comment
        // @ts-ignore
        Object.keys(address).forEach(key => address[key] === undefined && delete address[key]);
    }

    const latitude = effective?.type === LocationType.InPerson ? effective.location?.latitude : null;
    const longitude = effective?.type === LocationType.InPerson ? effective.location?.longitude : null;
    const geo = latitude != null && longitude != null ? {
        "@type": "http://schema.org/GeoCoordinates",
        latitude,
        longitude,
    } : null;

    const venueName = effective?.type === LocationType.InPerson
        ? (effective.location?.name || effective.location?.structured_address?.venue_name || null)
        : null;
    const placeName = venueName
        ?? (effective?.type === LocationType.InPerson && structuredAddress ? formatAddress(structuredAddress) : null);

    const includePlace = effective?.type === LocationType.InPerson && address && Object.keys(address).length > 1;
    const location = includePlace ? {
        "@type": "http://schema.org/Place",
        name: placeName,
        address,
        ...(geo ? {geo} : {}),
    } : {};

    const eventAttendanceMode = hasMixedModes
        ? "https://schema.org/MixedEventAttendanceMode"
        : effective?.type === LocationType.Online
            ? "https://schema.org/OnlineEventAttendanceMode"
            : effective?.type === LocationType.InPerson
                ? "https://schema.org/OfflineEventAttendanceMode"
                : undefined;

    const schemaOrgJSONLD = {
        "@context": "http://schema.org",
        "@type": "http://schema.org/Event",
        name: title,
        startDate,
        endDate,
        location,
        image: [image],
        description: description,
        keywords,
        organizer: {
            "@type": "http://schema.org/Organization",
            name: event.organizer?.name,
            url: event.organizer?.website
        },
        url,
        eventStatus: 'https://schema.org/EventScheduled',
        eventAttendanceMode,
        currency: event.currency,
        offers: products.map(product => ({
            "@type": "http://schema.org/Offer",
            url,
            price: product?.prices?.[0]?.price,
            priceCurrency: event.currency,
            validFrom: startDate,
            availability: product?.is_available ? "http://schema.org/InStock" : "http://schema.org/SoldOut",
        })),
    };

    return (
        <Helmet>
            <title>{event.status === 'DRAFT' ? 'DRAFT - ' + title : title}</title>
            <meta name="description" content={description}/>
            {keywords && <meta name="keywords" content={keywords}/>}
            <meta property="og:title" content={title}/>
            <meta property="og:description" content={description}/>
            {image && <meta property="og:image" content={image}/>}
            {url && <meta property="og:url" content={url}/>}
            <meta property="og:type" content="website"/>
            <meta name="author" content={event.organizer?.name}/>

            <meta name="twitter:title" content={title}/>
            <meta name="twitter:description" content={description}/>
            {image && <meta name="twitter:image" content={image}/>}
            <meta name="twitter:card" content="summary_large_image"/>

            <meta name="robots" content="index, follow"/>

            <link rel="canonical" href={url}/>

            <script type="application/ld+json">
                {JSON.stringify(schemaOrgJSONLD)}
            </script>
        </Helmet>
    );
}
