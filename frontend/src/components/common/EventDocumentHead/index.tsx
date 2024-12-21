/* eslint-disable lingui/no-unlocalized-strings */
import {Helmet} from "react-helmet-async";
import {Event} from "../../../types";
import {eventCoverImageUrl, eventHomepageUrl} from "../../../utilites/urlHelper.ts";
import {utcToTz} from "../../../utilites/dates.ts";

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

    const address = {
        "@type": "http://schema.org/PostalAddress",
        streetAddress: eventSettings?.location_details?.address_line_1,
        addressLocality: eventSettings?.location_details?.city,
        addressRegion: eventSettings?.location_details?.state_or_region,
        postalCode: eventSettings?.location_details?.zip_or_postal_code,
        addressCountry: eventSettings?.location_details?.country
    };

    // Filter out undefined address properties
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
    // @ts-ignore
    Object.keys(address).forEach(key => address[key] === undefined && delete address[key]);

    const location = eventSettings?.location_details && Object.keys(address).length > 1 ? {
        "@type": "http://schema.org/Place",
        name: event.location_details?.venue_name,
        address
    } : {};

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
        eventAttendanceMode: event.settings?.is_online_event ? "https://schema.org/OnlineEventAttendanceMode" : "https://schema.org/OfflineEventAttendanceMode",
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
