import {t} from "@lingui/macro";
import {ReactNode} from "react";
import {IconDeviceMobile, IconDeviceMobileCheck, IconDeviceMobileOff} from "@tabler/icons-react";
import {PhoneCollection} from "../types.ts";
import {ItemProps} from "../components/common/CustomSelect";

export const getPhoneCollectionLabel = (value: PhoneCollection): string => {
    switch (value) {
        case 'REQUIRED':
            return t`Required`;
        case 'OPTIONAL':
            return t`Optional`;
        default:
            return t`Don't ask`;
    }
};

export const getPhoneCollectionDescription = (value: PhoneCollection): string => {
    switch (value) {
        case 'REQUIRED':
            return t`Buyers must give a mobile number to complete online checkout. Box office and manually added orders can skip it.`;
        case 'OPTIONAL':
            return t`Buyers can add a mobile number if they want to.`;
        default:
            return t`Checkout doesn't ask for a mobile number.`;
    }
};

const PHONE_COLLECTION_ICONS: Record<PhoneCollection, ReactNode> = {
    OFF: <IconDeviceMobileOff/>,
    OPTIONAL: <IconDeviceMobile/>,
    REQUIRED: <IconDeviceMobileCheck/>,
};

export const getPhoneCollectionOptions = (): ItemProps[] => (['OFF', 'OPTIONAL', 'REQUIRED'] as PhoneCollection[]).map((value) => ({
    icon: PHONE_COLLECTION_ICONS[value],
    label: getPhoneCollectionLabel(value),
    value,
    description: getPhoneCollectionDescription(value),
}));
