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