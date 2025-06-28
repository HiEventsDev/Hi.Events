/* eslint-disable lingui/no-unlocalized-strings */
import {Helmet} from "react-helmet-async";
import {Organizer} from "../../../types";
import {organizerHomepageUrl} from "../../../utilites/urlHelper.ts";

interface OrganizerDocumentHeadProps {
    organizer: Organizer;
}

export const OrganizerDocumentHead = ({organizer}: OrganizerDocumentHeadProps) => {
    const organizerSettings = organizer.settings;
    const title = organizerSettings?.seo_title || `${organizer.name} - Events`;
    const description = organizerSettings?.seo_description || `Discover upcoming events by ${organizer.name}.`;
    const keywords = organizerSettings?.seo_keywords || `${organizer.name}, events, tickets, concerts, sell tickets online`;
    const logoImage = organizer.images?.find(img => img.type === 'ORGANIZER_LOGO')?.url;
    const coverImage = organizer.images?.find(img => img.type === 'ORGANIZER_COVER')?.url;
    const image = coverImage || logoImage;
    const url = organizerHomepageUrl(organizer);

    const address = organizerSettings?.location_details ? {
        "@type": "http://schema.org/PostalAddress",
        streetAddress: organizerSettings.location_details.address_line_1,
        addressLocality: organizerSettings.location_details.city,
        addressRegion: organizerSettings.location_details.state_or_region,
        postalCode: organizerSettings.location_details.zip_or_postal_code,
        addressCountry: organizerSettings.location_details.country
    } : undefined;

    // Filter out undefined address properties
    if (address) {
        Object.keys(address).forEach(key => {
            // @ts-ignore
            if (address[key] === undefined) {
                // @ts-ignore
                delete address[key];
            }
        });
    }

    const location = address && Object.keys(address).length > 1 ? {
        "@type": "http://schema.org/Place",
        name: organizerSettings?.location_details?.venue_name,
        address
    } : undefined;

    // Collect social media links
    const sameAs = [];
    if (organizerSettings?.website_url) {
        sameAs.push(organizerSettings.website_url);
    }
    if (organizerSettings?.social_media_handles) {
        const socialHandles = organizerSettings.social_media_handles;
        if (socialHandles.facebook) sameAs.push(`https://facebook.com/${socialHandles.facebook}`);
        if (socialHandles.twitter) sameAs.push(`https://twitter.com/${socialHandles.twitter}`);
        if (socialHandles.instagram) sameAs.push(`https://instagram.com/${socialHandles.instagram}`);
        if (socialHandles.linkedin) sameAs.push(`https://linkedin.com/company/${socialHandles.linkedin}`);
        if (socialHandles.youtube) sameAs.push(`https://youtube.com/@${socialHandles.youtube}`);
        if (socialHandles.github) sameAs.push(`https://github.com/${socialHandles.github}`);
    }

    const schemaOrgJSONLD = {
        "@context": "http://schema.org",
        "@type": "http://schema.org/Organization",
        name: organizer.name,
        description: description,
        url: url,
        logo: logoImage,
        image: image,
        location: location,
        sameAs: sameAs.length > 0 ? sameAs : undefined,
    };

    // Remove undefined properties
    Object.keys(schemaOrgJSONLD).forEach(key => {
        // @ts-ignore
        if (schemaOrgJSONLD[key] === undefined) {
            // @ts-ignore
            delete schemaOrgJSONLD[key];
        }
    });

    const allowIndexing = organizerSettings?.allow_search_engine_indexing !== false;

    return (
        <Helmet>
            <title>{title}</title>
            <meta name="description" content={description}/>
            {keywords && <meta name="keywords" content={keywords}/>}
            <meta property="og:title" content={title}/>
            <meta property="og:description" content={description}/>
            {image && <meta property="og:image" content={image}/>}
            <meta property="og:url" content={url}/>
            <meta property="og:type" content="website"/>
            <meta name="author" content={organizer.name}/>

            <meta name="twitter:title" content={title}/>
            <meta name="twitter:description" content={description}/>
            {image && <meta name="twitter:image" content={image}/>}
            <meta name="twitter:card" content="summary_large_image"/>

            <meta name="robots" content={allowIndexing ? "index, follow" : "noindex, nofollow"}/>

            <link rel="canonical" href={url}/>

            <script type="application/ld+json">
                {JSON.stringify(schemaOrgJSONLD)}
            </script>
        </Helmet>
    );
}
