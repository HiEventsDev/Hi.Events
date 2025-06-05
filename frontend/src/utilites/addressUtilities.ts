import {VenueAddress} from "../types.ts";

export const formatAddress = (address: VenueAddress) => {
    const addressLines = [
        address.address_line_1,
        address.address_line_2,
        address.city,
        address.state_or_region,
        address.zip_or_postal_code,
        address.country
    ];

    return addressLines.filter((line) => line).join(', ');
}

export const getShortLocationDisplay = (locationDetails?: VenueAddress) => {
    if (!locationDetails) return null;

    const parts = [];
    if (locationDetails.venue_name) {
        parts.push(locationDetails.venue_name);
    }
    if (locationDetails.city) {
        parts.push(locationDetails.city);
    }

    return parts.length > 0 ? parts.join(', ') : null;
};

export const getGoogleMapsUrl = (locationDetails: VenueAddress) => {
    if (!locationDetails) return '';
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(formatAddress(locationDetails))}`;
};
