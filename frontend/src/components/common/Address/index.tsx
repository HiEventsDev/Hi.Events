import {Address as AddressType} from "../../../types.ts";

type AddressProps = {
    address: AddressType;
};

export const Address = ({address}: AddressProps) => {
    const {address_line_1, address_line_2, city, state_or_region, country, zip_or_postal_code} = address;

    return (
        <div>
            {address_line_1 && <span>{address_line_1} {'  '}</span>}
            {address_line_2 && <span>{address_line_2} {'  '}</span>}
            {city && <span>{city} {'  '}</span>}
            {state_or_region && <span>{state_or_region} {'  '}</span>}
            {zip_or_postal_code && <span>{zip_or_postal_code} {'  '}</span>}
            {country && <span>{country} {'  '}</span>}
        </div>
    );
};
